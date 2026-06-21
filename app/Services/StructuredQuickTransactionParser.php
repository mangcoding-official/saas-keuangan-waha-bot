<?php

namespace App\Services;

use App\Enums\CategoryType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\TenantUser;
use Illuminate\Support\Carbon;

class StructuredQuickTransactionParser
{
    /**
     * @return array{
     *     status: string,
     *     route: string,
     *     reply_text: string,
     *     payload: ?array<string, mixed>,
     *     clarification_state: ?string,
     *     intent_type: ?string
     * }
     */
    public function parse(TenantUser $tenantUser, string $messageText, Carbon $messageTimestamp): array
    {
        $rawText = trim(preg_replace('/\s+/u', ' ', $messageText) ?? '');
        $lowerText = mb_strtolower($rawText);
        $now = $messageTimestamp->copy()->timezone($tenantUser->tenant->timezone);

        if ($rawText === '') {
            return $this->failed('parse_failed', $this->helpText());
        }

        if (in_array($lowerText, ['menu', 'bantuan'], true)) {
            return $this->failed('command_help', $this->helpText());
        }

        if (in_array($lowerText, ['masuk', 'keluar', 'transfer'], true)) {
            return $this->failed('guided_fallback', $this->guidedCommandText($lowerText));
        }

        $commandMap = [
            'masuk' => TransactionType::INCOME->value,
            'keluar' => TransactionType::EXPENSE->value,
            'transfer' => TransactionType::TRANSFER->value,
        ];

        $detectedCommands = [];

        foreach (array_keys($commandMap) as $command) {
            if (preg_match('/\b'.preg_quote($command, '/').'\b/u', $lowerText) === 1) {
                $detectedCommands[] = $command;
            }
        }

        if (count($detectedCommands) > 1) {
            return $this->clarification(
                'Pesan kamu memuat lebih dari satu tipe transaksi. Kirim satu transaksi per pesan, misalnya: `keluar 15rb makan`.',
                'clarify_mixed_type',
            );
        }

        $command = $detectedCommands[0] ?? null;

        if ($command === null || ! preg_match('/^'.preg_quote($command, '/').'\b/u', $lowerText)) {
            return $this->failed('parse_failed', $this->helpText());
        }

        $body = trim((string) preg_replace('/^'.preg_quote($command, '/').'\b\s*/iu', '', $rawText, 1));
        $amountMatch = $this->extractAmount($body);

        if ($amountMatch === null) {
            return $this->clarification(
                'Nominal belum terbaca. Pakai format seperti `keluar 15rb makan` atau `masuk 1,5 juta gaji`.',
                'clarify_amount',
                $commandMap[$command],
            );
        }

        $bodyWithoutAmount = trim(str_replace($amountMatch['matched_text'], ' ', $body));
        $dateMatch = $this->extractDate($bodyWithoutAmount, $now);
        $transactionDate = $dateMatch['date'] ?? $now->copy()->startOfDay();
        $bodyWithoutDate = trim($dateMatch['remaining_text'] ?? $bodyWithoutAmount);

        return $command === 'transfer'
            ? $this->parseTransfer($tenantUser, $rawText, $amountMatch['amount'], $transactionDate, $bodyWithoutDate)
            : $this->parseIncomeOrExpense(
                $tenantUser,
                $rawText,
                $commandMap[$command],
                $amountMatch['amount'],
                $transactionDate,
                $bodyWithoutDate,
            );
    }

    /**
     * @return array{
     *     status: string,
     *     route: string,
     *     reply_text: string,
     *     payload: ?array<string, mixed>
     * }
     */
    private function parseIncomeOrExpense(
        TenantUser $tenantUser,
        string $rawText,
        string $type,
        float $amount,
        Carbon $transactionDate,
        string $body
    ): array {
        $accounts = Account::query()
            ->where('tenant_id', $tenantUser->tenant_id)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        if ($accounts->isEmpty()) {
            return $this->clarification(
                'Belum ada akun aktif untuk tenant ini. Owner perlu mengaktifkan minimal satu akun.',
                $type === TransactionType::INCOME->value ? 'clarify_destination_account' : 'clarify_source_account',
                $type,
            );
        }

        $accountResolution = $this->resolveSingleAccount($body, $accounts->all());

        if ($accountResolution['ambiguous']) {
            return $this->clarification(
                'Nama akun yang dipakai ambigu. Sebutkan satu akun saja, misalnya `keluar 20rb makan via cash default`.',
                $type === TransactionType::INCOME->value ? 'clarify_destination_account' : 'clarify_source_account',
                $type,
            );
        }

        if ($accountResolution['explicit'] && $accountResolution['account'] === null) {
            return $this->clarification(
                'Akun yang kamu sebut belum dikenali. Pakai nama akun tenant yang aktif, misalnya `via cash default`.',
                $type === TransactionType::INCOME->value ? 'clarify_destination_account' : 'clarify_source_account',
                $type,
            );
        }

        $account = $accountResolution['account'] ?? $accounts->firstWhere('is_default', true);

        if ($account === null) {
            return $this->clarification(
                'Akun default tenant belum tersedia. Owner perlu menetapkan default account lebih dulu.',
                $type === TransactionType::INCOME->value ? 'clarify_destination_account' : 'clarify_source_account',
                $type,
            );
        }

        $categoryType = $type === TransactionType::INCOME->value ? CategoryType::INCOME : CategoryType::EXPENSE;
        $categories = Category::query()
            ->where('tenant_id', $tenantUser->tenant_id)
            ->where('type', $categoryType)
            ->where('is_active', true)
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();

        if ($categories->isEmpty()) {
            return $this->clarification(
                'Belum ada kategori aktif untuk tipe transaksi ini. Owner perlu menyiapkan kategori lebih dulu.',
                'clarify_category',
                $type,
            );
        }

        $category = $this->resolveCategory($body, $categories->all());

        if ($category === null) {
            $example = $type === TransactionType::INCOME->value
                ? 'masuk 1jt gaji'
                : 'keluar 20rb makan';

            return $this->clarification(
                'Kategori belum dikenali. Pakai kategori/keyword tenant, misalnya `'.$example.'`.',
                'clarify_category',
                $type,
            );
        }

        return [
            'status' => 'parsed',
            'route' => 'parsed',
            'reply_text' => '',
            'payload' => [
                'type' => $type,
                'amount' => $amount,
                'description' => $rawText,
                'transaction_date' => $transactionDate,
                'category_id' => $category->id,
                'account_id' => $account->id,
                'category_name' => $category->name,
                'account_name' => $account->name,
            ],
            'clarification_state' => null,
            'intent_type' => $type,
        ];
    }

    /**
     * @return array{
     *     status: string,
     *     route: string,
     *     reply_text: string,
     *     payload: ?array<string, mixed>
     * }
     */
    private function parseTransfer(
        TenantUser $tenantUser,
        string $rawText,
        float $amount,
        Carbon $transactionDate,
        string $body
    ): array {
        $accounts = Account::query()
            ->where('tenant_id', $tenantUser->tenant_id)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->all();

        if (count($accounts) < 2) {
            return $this->clarification(
                'Transfer butuh minimal dua akun aktif. Owner perlu menambah akun tujuan terlebih dulu.',
                'clarify_transfer_accounts',
                TransactionType::TRANSFER->value,
            );
        }

        $resolution = $this->resolveTransferAccounts($body, $accounts);

        if ($resolution['source_explicit'] && $resolution['source_account'] === null) {
            return $this->clarification(
                'Akun sumber belum dikenali. Contoh format: `transfer 50rb dari cash default ke bca operasional`.',
                'clarify_transfer_accounts',
                TransactionType::TRANSFER->value,
            );
        }

        if ($resolution['destination_explicit'] && $resolution['destination_account'] === null) {
            return $this->clarification(
                'Akun tujuan belum dikenali. Contoh format: `transfer 50rb dari cash default ke bca operasional`.',
                'clarify_transfer_accounts',
                TransactionType::TRANSFER->value,
            );
        }

        $sourceAccount = $resolution['source_account'];
        $destinationAccount = $resolution['destination_account'];

        if ($sourceAccount === null || $destinationAccount === null) {
            return $this->clarification(
                'Transfer harus menyebut akun asal dan tujuan. Contoh: `transfer 50rb dari cash default ke bca operasional`.',
                'clarify_transfer_accounts',
                TransactionType::TRANSFER->value,
            );
        }

        if ($sourceAccount->id === $destinationAccount->id) {
            return $this->clarification(
                'Akun asal dan tujuan transfer tidak boleh sama.',
                'clarify_transfer_accounts',
                TransactionType::TRANSFER->value,
            );
        }

        return [
            'status' => 'parsed',
            'route' => 'parsed',
            'reply_text' => '',
            'payload' => [
                'type' => TransactionType::TRANSFER->value,
                'amount' => $amount,
                'description' => $rawText,
                'transaction_date' => $transactionDate,
                'category_id' => null,
                'source_account_id' => $sourceAccount->id,
                'destination_account_id' => $destinationAccount->id,
                'source_account_name' => $sourceAccount->name,
                'destination_account_name' => $destinationAccount->name,
            ],
            'clarification_state' => null,
            'intent_type' => TransactionType::TRANSFER->value,
        ];
    }

    /**
     * @param  array<int, Account>  $accounts
     * @return array{account: ?Account, explicit: bool, ambiguous: bool}
     */
    private function resolveSingleAccount(string $text, array $accounts): array
    {
        $mentions = $this->findAccountMentions($text, $accounts);
        $hasMarker = preg_match('/\b(via|pakai|dari|ke|akun|rekening|dompet)\b/u', mb_strtolower($text)) === 1;

        if (count($mentions) > 1) {
            return [
                'account' => null,
                'explicit' => true,
                'ambiguous' => true,
            ];
        }

        return [
            'account' => $mentions[0]['account'] ?? null,
            'explicit' => $hasMarker || $mentions !== [],
            'ambiguous' => false,
        ];
    }

    /**
     * @param  array<int, Account>  $accounts
     * @return array{
     *     source_account: ?Account,
     *     destination_account: ?Account,
     *     source_explicit: bool,
     *     destination_explicit: bool
     * }
     */
    private function resolveTransferAccounts(string $text, array $accounts): array
    {
        $sourceMarkers = ['dari', 'pakai', 'via'];
        $destinationMarkers = ['masuk ke', 'ke', 'tujuan'];

        $sourceSegment = $this->extractSegmentAfterMarker($text, $sourceMarkers);
        $destinationSegment = $this->extractSegmentAfterMarker($text, $destinationMarkers);

        $sourceMentions = $sourceSegment === null ? [] : $this->findAccountMentions($sourceSegment, $accounts);
        $destinationMentions = $destinationSegment === null ? [] : $this->findAccountMentions($destinationSegment, $accounts);
        $allMentions = $this->findAccountMentions($text, $accounts);

        $sourceAccount = $sourceMentions[0]['account'] ?? null;
        $destinationAccount = $destinationMentions[0]['account'] ?? null;

        if ($sourceAccount === null && $destinationAccount !== null && count($allMentions) === 2) {
            $sourceAccount = $allMentions[0]['account']->id === $destinationAccount->id
                ? $allMentions[1]['account']
                : $allMentions[0]['account'];
        }

        if ($destinationAccount === null && $sourceAccount !== null && count($allMentions) === 2) {
            $destinationAccount = $allMentions[0]['account']->id === $sourceAccount->id
                ? $allMentions[1]['account']
                : $allMentions[0]['account'];
        }

        if ($sourceAccount === null && $destinationAccount === null && count($allMentions) === 2) {
            $sourceAccount = $allMentions[0]['account'];
            $destinationAccount = $allMentions[1]['account'];
        }

        return [
            'source_account' => $sourceAccount,
            'destination_account' => $destinationAccount,
            'source_explicit' => $sourceSegment !== null,
            'destination_explicit' => $destinationSegment !== null,
        ];
    }

    /**
     * @param  array<int, Category>  $categories
     */
    private function resolveCategory(string $text, array $categories): ?Category
    {
        $normalizedText = $this->normalizeForMatch($text);
        $bestMatch = null;
        $bestLength = 0;

        foreach ($categories as $category) {
            $candidates = [$category->name, ...($category->keywords ?? [])];

            foreach ($candidates as $candidate) {
                $needle = $this->normalizeForMatch((string) $candidate);

                if ($needle === '') {
                    continue;
                }

                if (str_contains($normalizedText, $needle) && strlen($needle) > $bestLength) {
                    $bestMatch = $category;
                    $bestLength = strlen($needle);
                }
            }
        }

        return $bestMatch;
    }

    /**
     * @param  array<int, Account>  $accounts
     * @return array<int, array{account: Account, position: int}>
     */
    private function findAccountMentions(string $text, array $accounts): array
    {
        $normalizedText = $this->normalizeForMatch($text);
        $mentions = [];

        foreach ($accounts as $account) {
            $needle = $this->normalizeForMatch($account->name);

            if ($needle === '') {
                continue;
            }

            $position = strpos($normalizedText, $needle);

            if ($position === false) {
                continue;
            }

            $mentions[] = [
                'account' => $account,
                'position' => $position,
            ];
        }

        usort($mentions, static fn (array $left, array $right): int => $left['position'] <=> $right['position']);

        return $mentions;
    }

    /**
     * @param  array<int, string>  $markers
     */
    private function extractSegmentAfterMarker(string $text, array $markers): ?string
    {
        $lowerText = mb_strtolower($text);

        foreach ($markers as $marker) {
            $position = mb_strpos($lowerText, $marker);

            if ($position === false) {
                continue;
            }

            return trim(mb_substr($text, $position + mb_strlen($marker)));
        }

        return null;
    }

    /**
     * @return array{matched_text: string, amount: float}|null
     */
    private function extractAmount(string $text): ?array
    {
        if (preg_match('/^(\d[\d.,]*(?:\s*(?:rb|ribu|k|jt|juta))?)/iu', $text, $matches) !== 1) {
            return null;
        }

        $matchedText = trim($matches[1]);
        $compact = preg_replace('/\s+/u', '', mb_strtolower($matchedText)) ?? '';
        $multiplier = 1;

        if (str_contains($compact, 'juta') || str_contains($compact, 'jt')) {
            $multiplier = 1000000;
            $compact = str_replace(['juta', 'jt'], '', $compact);
        } elseif (str_contains($compact, 'ribu') || str_contains($compact, 'rb') || preg_match('/k$/', $compact) === 1) {
            $multiplier = 1000;
            $compact = str_replace(['ribu', 'rb', 'k'], '', $compact);
        }

        $compact = trim($compact);

        if ($compact === '') {
            return null;
        }

        if ($multiplier > 1) {
            $normalized = str_replace(',', '.', $compact);
        } else {
            $normalized = str_replace(',', '', str_replace('.', '', $compact));
        }

        if ($multiplier > 1 && preg_match('/^\d+(?:\.\d+)?$/', $normalized) === 1) {
            $amount = (float) $normalized * $multiplier;
        } elseif (preg_match('/^\d+(?:\.\d+)?$/', $normalized) === 1) {
            $amount = (float) $normalized;
        } else {
            return null;
        }

        return [
            'matched_text' => $matchedText,
            'amount' => $amount,
        ];
    }

    /**
     * @return array{date: ?Carbon, remaining_text: string}
     */
    private function extractDate(string $text, Carbon $now): array
    {
        $normalized = mb_strtolower($text);
        $remaining = $text;

        if (preg_match('/\bhari ini\b/u', $normalized) === 1 || preg_match('/\bsekarang\b/u', $normalized) === 1) {
            $remaining = preg_replace('/\b(hari ini|sekarang)\b/u', ' ', $remaining) ?? $remaining;

            return [
                'date' => $now->copy()->startOfDay(),
                'remaining_text' => trim(preg_replace('/\s+/u', ' ', $remaining) ?? $remaining),
            ];
        }

        if (preg_match('/\bkemarin\b/u', $normalized) === 1) {
            $remaining = preg_replace('/\bkemarin\b/u', ' ', $remaining) ?? $remaining;

            return [
                'date' => $now->copy()->subDay()->startOfDay(),
                'remaining_text' => trim(preg_replace('/\s+/u', ' ', $remaining) ?? $remaining),
            ];
        }

        if (preg_match('/\b(\d{4})-(\d{2})-(\d{2})\b/u', $normalized, $matches) === 1) {
            $remaining = str_replace($matches[0], ' ', $remaining);

            return [
                'date' => Carbon::createFromDate((int) $matches[1], (int) $matches[2], (int) $matches[3], $now->timezone),
                'remaining_text' => trim(preg_replace('/\s+/u', ' ', $remaining) ?? $remaining),
            ];
        }

        if (preg_match('/\b(\d{1,2})\/(\d{1,2})\/(\d{4})\b/u', $normalized, $matches) === 1) {
            $remaining = str_replace($matches[0], ' ', $remaining);

            return [
                'date' => Carbon::createFromDate((int) $matches[3], (int) $matches[2], (int) $matches[1], $now->timezone),
                'remaining_text' => trim(preg_replace('/\s+/u', ' ', $remaining) ?? $remaining),
            ];
        }

        if (preg_match('/\btanggal\s+(\d{1,2})\b/u', $normalized, $matches) === 1) {
            $remaining = preg_replace('/\btanggal\s+\d{1,2}\b/u', ' ', $remaining) ?? $remaining;

            return [
                'date' => Carbon::createFromDate((int) $now->format('Y'), (int) $now->format('m'), (int) $matches[1], $now->timezone),
                'remaining_text' => trim(preg_replace('/\s+/u', ' ', $remaining) ?? $remaining),
            ];
        }

        if (preg_match('/\b(\d{1,2})\s+(januari|februari|maret|april|mei|juni|juli|agustus|september|oktober|november|desember)(?:\s+(\d{4}))?\b/u', $normalized, $matches) === 1) {
            $month = $this->indonesianMonthNumber($matches[2]);
            $year = isset($matches[3]) ? (int) $matches[3] : (int) $now->format('Y');
            $remaining = str_replace($matches[0], ' ', $remaining);

            return [
                'date' => Carbon::createFromDate($year, $month, (int) $matches[1], $now->timezone),
                'remaining_text' => trim(preg_replace('/\s+/u', ' ', $remaining) ?? $remaining),
            ];
        }

        return [
            'date' => null,
            'remaining_text' => trim(preg_replace('/\s+/u', ' ', $remaining) ?? $remaining),
        ];
    }

    private function indonesianMonthNumber(string $month): int
    {
        return match (mb_strtolower($month)) {
            'januari' => 1,
            'februari' => 2,
            'maret' => 3,
            'april' => 4,
            'mei' => 5,
            'juni' => 6,
            'juli' => 7,
            'agustus' => 8,
            'september' => 9,
            'oktober' => 10,
            'november' => 11,
            'desember' => 12,
            default => 1,
        };
    }

    private function normalizeForMatch(string $value): string
    {
        $normalized = mb_strtolower($value);
        $normalized = preg_replace('/[^a-z0-9]+/u', ' ', $normalized) ?? $normalized;

        return trim(preg_replace('/\s+/u', ' ', $normalized) ?? $normalized);
    }

    /**
     * @return array{
     *     status: string,
     *     route: string,
     *     reply_text: string,
     *     payload: null,
     *     clarification_state: null,
     *     intent_type: null
     * }
     */
    private function failed(string $route, string $replyText): array
    {
        return [
            'status' => 'failed',
            'route' => $route,
            'reply_text' => $replyText,
            'payload' => null,
            'clarification_state' => null,
            'intent_type' => null,
        ];
    }

    /**
     * @return array{
     *     status: string,
     *     route: string,
     *     reply_text: string,
     *     payload: null,
     *     clarification_state: string,
     *     intent_type: ?string
     * }
     */
    private function clarification(string $replyText, string $state = 'clarify_amount', ?string $intentType = null): array
    {
        return [
            'status' => 'needs_clarification',
            'route' => 'needs_clarification',
            'reply_text' => $replyText,
            'payload' => null,
            'clarification_state' => $state,
            'intent_type' => $intentType,
        ];
    }

    private function helpText(): string
    {
        return implode("\n", [
            'Perintah tersedia:',
            'masuk [nominal] [kategori]',
            'keluar [nominal] [kategori]',
            'masuk [nominal] [kategori] [tanggal]',
            'keluar [nominal] [kategori] [tanggal]',
            'transfer [nominal] dari [akun] ke [akun]',
            '',
            'Contoh:',
            'masuk 15rb gaji',
            'keluar 20rb makan 15 juni',
            'transfer 50rb dari cash default ke bca operasional',
            'ketik menu atau bantuan untuk melihat perintah.',
        ]);
    }

    private function guidedCommandText(string $command): string
    {
        return match ($command) {
            'masuk' => 'Format cepat pemasukan: `masuk 15000 gaji` atau `masuk 1,5 juta bonus 15 juni`.',
            'keluar' => 'Format cepat pengeluaran: `keluar 20rb makan` atau `keluar 75rb transport via cash default`.',
            'transfer' => 'Format cepat transfer: `transfer 50rb dari cash default ke bca operasional`.',
            default => $this->helpText(),
        };
    }
}
