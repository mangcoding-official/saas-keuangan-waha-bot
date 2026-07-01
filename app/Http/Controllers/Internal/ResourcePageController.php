<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Support\Navigation\PlatformAdminNavigation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ResourcePageController extends Controller
{
    public function show(Request $request, string $page): View
    {
        return match ($page) {
            'tenants.index' => $this->showTenants($request),
            'users.index' => $this->showUsers($request),
            'sessions.index' => $this->showSessions($request),
            'transactions.index' => $this->showTransactions($request),
            'attachments.index' => $this->showAttachments($request),
            'audit.tenant-facing.index' => $this->showTenantAudit($request),
            'audit.platform.index' => $this->showPlatformAudit($request),
            'waha.index' => $this->showWaha($request),
            default => abort(404),
        };
    }

    public function showTenant(Request $request, int $tenantId): View
    {
        $tenant = DB::table('tenants')->where('id', $tenantId)->first();

        abort_if($tenant === null, 404);

        $summary = [
            [
                'label' => 'Total users',
                'value' => (string) DB::table('tenant_users')->where('tenant_id', $tenantId)->count(),
                'note' => 'Owner dan member tenant',
                'tone' => 'neutral',
            ],
            [
                'label' => 'Pending verification',
                'value' => (string) DB::table('tenant_users')
                    ->where('tenant_id', $tenantId)
                    ->where('verification_status', 'pending_verification')
                    ->count(),
                'note' => 'Butuh follow-up support',
                'tone' => 'alert',
            ],
            [
                'label' => 'Active sessions',
                'value' => (string) DB::table('conversation_sessions')
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'active')
                    ->count(),
                'note' => 'Guided chat masih berjalan',
                'tone' => 'neutral',
            ],
            [
                'label' => 'Completed tx',
                'value' => (string) DB::table('transactions')
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'completed')
                    ->count(),
                'note' => 'Transaksi tersimpan',
                'tone' => 'neutral',
            ],
        ];

        $users = DB::table('tenant_users')
            ->where('tenant_id', $tenantId)
            ->orderByRaw("case when role = 'owner' then 0 else 1 end")
            ->orderBy('name')
            ->limit(8)
            ->get()
            ->map(fn (object $row): array => [
                'title' => $row->name,
                'meta' => strtoupper((string) $row->role).' · '.$row->whatsapp_number,
                'badges' => [
                    ['label' => (string) $row->user_status, 'tone' => $row->user_status === 'active' ? 'success' : 'warning'],
                    ['label' => (string) $row->verification_status, 'tone' => $row->verification_status === 'verified' ? 'success' : 'warning'],
                ],
            ])
            ->all();

        $sessions = DB::table('conversation_sessions')
            ->join('tenant_users', 'tenant_users.id', '=', 'conversation_sessions.tenant_user_id')
            ->where('conversation_sessions.tenant_id', $tenantId)
            ->orderByDesc('conversation_sessions.last_message_at')
            ->limit(6)
            ->get([
                'conversation_sessions.id',
                'conversation_sessions.status',
                'conversation_sessions.current_state',
                'conversation_sessions.intent_type',
                'conversation_sessions.last_message_at',
                'conversation_sessions.expires_at',
                'tenant_users.name as user_name',
            ])
            ->map(fn (object $row): array => [
                'title' => 'Session #'.$row->id.' · '.$row->user_name,
                'meta' => strtoupper((string) $row->status).' · '.$row->current_state,
                'lines' => [
                    'Intent: '.($row->intent_type ?: '-'),
                    'Last message: '.$this->formatDateTime($row->last_message_at),
                    'Expires: '.$this->formatDateTime($row->expires_at),
                ],
            ])
            ->all();

        $transactions = DB::table('transactions')
            ->join('tenant_users', 'tenant_users.id', '=', 'transactions.recorded_by_user_id')
            ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
            ->where('transactions.tenant_id', $tenantId)
            ->orderByDesc('transactions.transaction_date')
            ->orderByDesc('transactions.id')
            ->limit(8)
            ->get([
                'transactions.id',
                'transactions.type',
                'transactions.amount',
                'transactions.status',
                'transactions.transaction_date',
                'tenant_users.name as user_name',
                'categories.name as category_name',
            ])
            ->map(fn (object $row): array => [
                'title' => 'Tx #'.$row->id.' · '.strtoupper((string) $row->type),
                'meta' => 'Rp '.$this->formatCurrency((float) $row->amount).' · '.$this->formatDate($row->transaction_date),
                'badges' => [
                    ['label' => (string) $row->status, 'tone' => $row->status === 'completed' ? 'success' : 'warning'],
                ],
                'lines' => [
                    'Category: '.($row->category_name ?: '-'),
                    'Recorder: '.$row->user_name,
                ],
            ])
            ->all();

        $attachments = DB::table('attachments')
            ->join('tenant_users', 'tenant_users.id', '=', 'attachments.uploaded_by_user_id')
            ->leftJoin('attachment_transaction', 'attachment_transaction.attachment_id', '=', 'attachments.id')
            ->where('attachments.tenant_id', $tenantId)
            ->groupBy([
                'attachments.id',
                'attachments.original_file_name',
                'attachments.mime_type',
                'attachments.file_size',
                'attachments.created_at',
                'tenant_users.name',
            ])
            ->orderByDesc('attachments.created_at')
            ->limit(8)
            ->get([
                'attachments.id',
                'attachments.original_file_name',
                'attachments.mime_type',
                'attachments.file_size',
                'attachments.created_at',
                'tenant_users.name as user_name',
                DB::raw('count(attachment_transaction.transaction_id) as transaction_count'),
            ])
            ->map(fn (object $row): array => [
                'title' => 'Attachment #'.$row->id.' · '.($row->original_file_name ?: 'image'),
                'meta' => $row->mime_type.' · '.$this->formatBytes((int) $row->file_size),
                'lines' => [
                    'Uploader: '.$row->user_name,
                    'Linked tx: '.$row->transaction_count,
                    'Uploaded: '.$this->formatDateTime($row->created_at),
                ],
            ])
            ->all();

        $recentAudit = DB::table('audit_logs')
            ->leftJoin('tenant_users', 'tenant_users.id', '=', 'audit_logs.actor_tenant_user_id')
            ->leftJoin('platform_admin_users', 'platform_admin_users.id', '=', 'audit_logs.actor_platform_admin_user_id')
            ->where('audit_logs.tenant_id', $tenantId)
            ->orderByDesc('audit_logs.created_at')
            ->limit(8)
            ->get([
                'audit_logs.action',
                'audit_logs.entity_type',
                'audit_logs.entity_id',
                'audit_logs.actor_source',
                'audit_logs.created_at',
                'tenant_users.name as tenant_actor_name',
                'platform_admin_users.name as platform_actor_name',
            ])
            ->map(fn (object $row): array => [
                'title' => Str::of((string) $row->action)->replace('_', ' ')->title()->toString(),
                'meta' => Str::of((string) $row->entity_type)->replace('_', ' ')->title()->toString().' #'.$row->entity_id,
                'lines' => [
                    'Actor: '.($row->tenant_actor_name ?: $row->platform_actor_name ?: strtoupper((string) $row->actor_source)),
                    'Time: '.$this->formatDateTime($row->created_at),
                ],
            ])
            ->all();

        $botAssignments = DB::table('tenant_bot_assignments')
            ->join('bot_instances', 'bot_instances.id', '=', 'tenant_bot_assignments.bot_instance_id')
            ->where('tenant_bot_assignments.tenant_id', $tenantId)
            ->whereNull('tenant_bot_assignments.unassigned_at')
            ->orderByDesc('tenant_bot_assignments.assigned_at')
            ->get([
                'bot_instances.name',
                'bot_instances.connection_status',
                'bot_instances.qr_status',
                'tenant_bot_assignments.assigned_at',
            ])
            ->map(fn (object $row): array => [
                'title' => $row->name,
                'meta' => strtoupper((string) $row->connection_status).' · '.strtoupper((string) $row->qr_status),
                'lines' => ['Assigned at: '.$this->formatDateTime($row->assigned_at)],
            ])
            ->all();

        return view('internal.tenant-detail', [
            ...$this->baseViewData($request),
            'page' => [
                'title' => $tenant->name,
                'description' => 'Detail tenant lintas user, transaksi, attachment, audit, dan assignment bot.',
                'eyebrow' => 'Tenant Detail',
            ],
            'toolbar' => [
                'search_label' => 'Lihat area operasional tenant',
                'search_placeholder' => 'Tenant detail view',
                'secondary_action' => [
                    'label' => 'Back to tenants',
                    'href' => route('internal.tenants.index'),
                    'variant' => 'secondary',
                ],
                'primary_action' => [
                    'label' => 'Open users',
                    'href' => route('internal.users.index'),
                    'variant' => 'primary',
                ],
            ],
            'summary' => $summary,
            'tenantMeta' => [
                'type' => strtoupper((string) $tenant->tenant_type),
                'timezone' => $tenant->timezone,
                'tenant_status' => (string) $tenant->tenant_status,
                'service_plan' => (string) $tenant->service_plan,
                'service_status' => (string) $tenant->service_status,
                'ai_addon_status' => (string) $tenant->ai_addon_status,
                'created_at' => $this->formatDateTime($tenant->created_at),
                'updated_at' => $this->formatDateTime($tenant->updated_at),
            ],
            'users' => $users,
            'sessions' => $sessions,
            'transactions' => $transactions,
            'attachments' => $attachments,
            'recentAudit' => $recentAudit,
            'botAssignments' => $botAssignments,
        ]);
    }

    private function showTenants(Request $request): View
    {
        $rows = DB::table('tenants')
            ->leftJoin('tenant_users', 'tenant_users.tenant_id', '=', 'tenants.id')
            ->leftJoin('conversation_sessions', function ($join): void {
                $join->on('conversation_sessions.tenant_id', '=', 'tenants.id')
                    ->where('conversation_sessions.status', '=', 'active');
            })
            ->groupBy([
                'tenants.id',
                'tenants.name',
                'tenants.tenant_type',
                'tenants.tenant_status',
                'tenants.service_plan',
                'tenants.service_status',
                'tenants.timezone',
                'tenants.updated_at',
            ])
            ->orderBy('tenants.name')
            ->get([
                'tenants.id',
                'tenants.name',
                'tenants.tenant_type',
                'tenants.tenant_status',
                'tenants.service_plan',
                'tenants.service_status',
                'tenants.timezone',
                'tenants.updated_at',
                DB::raw('count(distinct tenant_users.id) as user_count'),
                DB::raw("sum(case when tenant_users.verification_status = 'pending_verification' then 1 else 0 end) as pending_count"),
                DB::raw('count(distinct conversation_sessions.id) as active_session_count'),
            ]);

        return $this->renderResourceIndex($request, [
            'page' => [
                'title' => 'Tenants',
                'description' => 'Pantau status tenant, service, user, dan guided session dari area internal.',
                'eyebrow' => 'Internal Module',
            ],
            'toolbar' => [
                'search_label' => 'Cari tenant atau status service',
                'search_placeholder' => 'Search tenant, plan, or status',
            ],
            'summary' => [
                ['label' => 'Total tenants', 'value' => (string) $rows->count(), 'note' => 'Semua workspace tenant', 'tone' => 'neutral'],
                ['label' => 'Active service', 'value' => (string) $rows->where('service_status', 'active')->count(), 'note' => 'Service berjalan normal', 'tone' => 'neutral'],
                ['label' => 'Need review', 'value' => (string) $rows->filter(fn (object $row): bool => $row->service_status !== 'active' || $row->tenant_status !== 'active')->count(), 'note' => 'Suspended, ended, atau tenant inactive', 'tone' => 'alert'],
                ['label' => 'Pending users', 'value' => (string) $rows->sum(fn (object $row): int => (int) $row->pending_count), 'note' => 'Verification belum selesai', 'tone' => 'alert'],
            ],
            'table' => [
                'headers' => ['Tenant', 'Status', 'Users', 'Sessions', 'Updated', 'Action'],
                'rows' => $rows->map(fn (object $row): array => [
                    'cells' => [
                        [
                            'primary' => $row->name,
                            'lines' => [
                                strtoupper((string) $row->tenant_type).' · '.$row->timezone,
                                'Plan: '.strtoupper((string) $row->service_plan),
                            ],
                        ],
                        [
                            'badges' => [
                                ['label' => (string) $row->tenant_status, 'tone' => $row->tenant_status === 'active' ? 'success' : 'warning'],
                                ['label' => (string) $row->service_status, 'tone' => $row->service_status === 'active' ? 'success' : 'warning'],
                            ],
                        ],
                        [
                            'primary' => (string) $row->user_count,
                            'lines' => ['Pending verification: '.(int) $row->pending_count],
                        ],
                        [
                            'primary' => (string) $row->active_session_count,
                            'lines' => ['Active guided chat'],
                        ],
                        [
                            'primary' => $this->formatDateTime($row->updated_at),
                        ],
                    ],
                    'actions' => [
                        ['label' => 'Detail', 'href' => route('internal.tenants.show', $row->id), 'variant' => 'secondary'],
                    ],
                ])->all(),
            ],
        ]);
    }

    private function showUsers(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $summaryBaseQuery = DB::table('tenant_users');

        $rows = DB::table('tenant_users')
            ->join('tenants', 'tenants.id', '=', 'tenant_users.tenant_id')
            ->leftJoin('activation_codes', function ($join): void {
                $join->on('activation_codes.tenant_user_id', '=', 'tenant_users.id')
                    ->where('activation_codes.active_lock', '=', 1);
            });

        if ($search !== '') {
            $rows->where(function ($query) use ($search): void {
                $query
                    ->where('tenant_users.name', 'like', '%'.$search.'%')
                    ->orWhere('tenants.name', 'like', '%'.$search.'%')
                    ->orWhere('tenant_users.whatsapp_number', 'like', '%'.$search.'%');
            });
        }

        $rows = $rows
            ->orderByDesc('tenant_users.created_at')
            ->paginate(10, [
                'tenant_users.id',
                'tenant_users.tenant_id',
                'tenant_users.name',
                'tenant_users.role',
                'tenant_users.user_status',
                'tenant_users.verification_status',
                'tenant_users.whatsapp_number',
                'tenant_users.last_login_at',
                'tenant_users.created_at',
                'tenants.name as tenant_name',
                'activation_codes.code_last4',
                'activation_codes.expires_at as activation_expires_at',
            ])
            ->withQueryString();

        $showId = (int) $request->query('show', 0);
        $detail = $showId > 0 ? $this->buildUserDetail($showId) : null;

        return view('internal.users.index', [
            ...$this->baseViewData($request),
            'page' => [
                'title' => 'Manajemen User Tenant',
                'description' => 'Pantau user lintas tenant, status akses, dan activation code dari satu halaman yang lebih operasional.',
                'eyebrow' => 'Internal Module',
            ],
            'summary' => [
                'total_users' => (int) (clone $summaryBaseQuery)->count(),
                'owner_count' => (int) (clone $summaryBaseQuery)->where('role', 'owner')->count(),
                'member_count' => (int) (clone $summaryBaseQuery)->where('role', 'member')->count(),
                'pending_count' => (int) (clone $summaryBaseQuery)->where('verification_status', 'pending_verification')->count(),
            ],
            'search' => $search,
            'users' => $rows->through(fn (object $row): array => [
                'id' => (int) $row->id,
                'tenant_id' => (int) $row->tenant_id,
                'name' => $row->name,
                'tenant_name' => $row->tenant_name,
                'initials' => Str::of((string) $row->name)
                    ->explode(' ')
                    ->filter()
                    ->take(2)
                    ->map(fn (string $segment): string => Str::upper(Str::substr($segment, 0, 1)))
                    ->implode(''),
                'role_key' => (string) $row->role,
                'role_label' => strtoupper((string) $row->role),
                'user_status_key' => (string) $row->user_status,
                'user_status_label' => $row->user_status === 'active' ? 'Aktif' : 'Nonaktif',
                'verification_status_key' => (string) $row->verification_status,
                'verification_status_label' => $row->verification_status === 'verified' ? 'Terverifikasi' : 'Menunggu',
                'whatsapp_number' => $row->whatsapp_number,
                'last_active_at' => $this->formatDateTime($row->last_login_at),
                'created_at' => $this->formatDateTime($row->created_at),
                'activation_code' => $row->code_last4 ? 'KAS-'.$row->code_last4 : '-',
                'activation_note' => $row->activation_expires_at
                    ? 'Berlaku sampai '.$this->formatDateTime($row->activation_expires_at)
                    : 'Tidak ada code aktif',
                'can_manage_verification' => $row->verification_status === 'pending_verification',
            ]),
            'detail' => $detail,
        ]);
    }

    private function showSessions(Request $request): View
    {
        $rows = DB::table('conversation_sessions')
            ->join('tenant_users', 'tenant_users.id', '=', 'conversation_sessions.tenant_user_id')
            ->join('tenants', 'tenants.id', '=', 'conversation_sessions.tenant_id')
            ->orderByDesc('conversation_sessions.last_message_at')
            ->limit(40)
            ->get([
                'conversation_sessions.id',
                'conversation_sessions.tenant_id',
                'conversation_sessions.status',
                'conversation_sessions.current_state',
                'conversation_sessions.intent_type',
                'conversation_sessions.source_message_id',
                'conversation_sessions.last_message_at',
                'conversation_sessions.expires_at',
                'conversation_sessions.draft_payload',
                'tenant_users.name as user_name',
                'tenants.name as tenant_name',
            ]);

        $showId = (int) $request->query('show', 0);
        $detail = $showId > 0 ? $this->buildSessionDetail($showId) : null;

        return $this->renderResourceIndex($request, [
            'page' => [
                'title' => 'Sessions',
                'description' => 'Pantau guided chat state, timeout, dan draft payload yang sedang berjalan.',
                'eyebrow' => 'Internal Module',
            ],
            'toolbar' => [
                'search_label' => 'Cari session atau current state',
                'search_placeholder' => 'Search session, state, or tenant',
            ],
            'summary' => [
                ['label' => 'Visible sessions', 'value' => (string) $rows->count(), 'note' => '40 session terbaru', 'tone' => 'neutral'],
                ['label' => 'Active', 'value' => (string) $rows->where('status', 'active')->count(), 'note' => 'Masih menerima input', 'tone' => 'neutral'],
                ['label' => 'Expired', 'value' => (string) $rows->where('status', 'expired')->count(), 'note' => 'Sudah timeout', 'tone' => 'alert'],
                ['label' => 'Need cleanup', 'value' => (string) $rows->filter(fn (object $row): bool => $row->status === 'active' && Carbon::parse((string) $row->expires_at)->isPast())->count(), 'note' => 'Active tapi lewat expiry', 'tone' => 'alert'],
            ],
            'table' => [
                'headers' => ['Tenant / User', 'Status', 'State', 'Intent', 'Last message', 'Action'],
                'rows' => $rows->map(fn (object $row): array => [
                    'cells' => [
                        [
                            'primary' => $row->tenant_name.' / '.$row->user_name,
                            'lines' => ['Session #'.$row->id, 'Source message: '.($row->source_message_id ?: '-')],
                        ],
                        [
                            'badges' => [
                                ['label' => (string) $row->status, 'tone' => $row->status === 'active' ? 'success' : 'warning'],
                            ],
                        ],
                        [
                            'primary' => $row->current_state,
                        ],
                        [
                            'primary' => $row->intent_type ?: '-',
                        ],
                        [
                            'primary' => $this->formatDateTime($row->last_message_at),
                            'lines' => ['Expires: '.$this->formatDateTime($row->expires_at)],
                        ],
                    ],
                    'actions' => [
                        ['label' => 'Inspect', 'href' => route('internal.sessions.index', ['show' => $row->id]), 'variant' => 'secondary'],
                        ['label' => 'Tenant', 'href' => route('internal.tenants.show', $row->tenant_id), 'variant' => 'ghost'],
                    ],
                ])->all(),
            ],
            'detail' => $detail,
        ]);
    }

    private function showTransactions(Request $request): View
    {
        $rows = DB::table('transactions')
            ->join('tenants', 'tenants.id', '=', 'transactions.tenant_id')
            ->join('tenant_users', 'tenant_users.id', '=', 'transactions.recorded_by_user_id')
            ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
            ->leftJoin('attachment_transaction', 'attachment_transaction.transaction_id', '=', 'transactions.id')
            ->groupBy([
                'transactions.id',
                'transactions.tenant_id',
                'transactions.type',
                'transactions.amount',
                'transactions.status',
                'transactions.transaction_date',
                'transactions.voided_at',
                'tenants.name',
                'tenant_users.name',
                'categories.name',
            ])
            ->orderByDesc('transactions.transaction_date')
            ->orderByDesc('transactions.id')
            ->limit(40)
            ->get([
                'transactions.id',
                'transactions.tenant_id',
                'transactions.type',
                'transactions.amount',
                'transactions.status',
                'transactions.transaction_date',
                'transactions.voided_at',
                'tenants.name as tenant_name',
                'tenant_users.name as user_name',
                'categories.name as category_name',
                DB::raw('count(attachment_transaction.attachment_id) as attachment_count'),
            ]);

        $showId = (int) $request->query('show', 0);
        $detail = $showId > 0 ? $this->buildTransactionDetail($showId) : null;

        return $this->renderResourceIndex($request, [
            'page' => [
                'title' => 'Transactions',
                'description' => 'Investigasi transaksi lintas tenant lengkap dengan attachment dan audit terkait.',
                'eyebrow' => 'Internal Module',
            ],
            'toolbar' => [
                'search_label' => 'Cari transaksi atau tenant',
                'search_placeholder' => 'Search tx, tenant, or category',
            ],
            'summary' => [
                ['label' => 'Visible tx', 'value' => (string) $rows->count(), 'note' => '40 transaksi terbaru', 'tone' => 'neutral'],
                ['label' => 'Completed', 'value' => (string) $rows->where('status', 'completed')->count(), 'note' => 'Masuk laporan tenant', 'tone' => 'neutral'],
                ['label' => 'Voided', 'value' => (string) $rows->whereNotNull('voided_at')->count(), 'note' => 'Perlu audit saat investigasi', 'tone' => 'alert'],
                ['label' => 'With proof', 'value' => (string) $rows->filter(fn (object $row): bool => (int) $row->attachment_count > 0)->count(), 'note' => 'Lampiran terhubung', 'tone' => 'neutral'],
            ],
            'table' => [
                'headers' => ['Tenant / Tx', 'Type', 'Amount', 'Category', 'Recorder', 'Action'],
                'rows' => $rows->map(fn (object $row): array => [
                    'cells' => [
                        [
                            'primary' => $row->tenant_name.' / Tx #'.$row->id,
                            'lines' => [$this->formatDate($row->transaction_date)],
                        ],
                        [
                            'badges' => [
                                ['label' => strtoupper((string) $row->type), 'tone' => $row->type === 'expense' ? 'warning' : 'success'],
                                ['label' => (string) $row->status, 'tone' => $row->status === 'completed' ? 'success' : 'warning'],
                            ],
                        ],
                        [
                            'primary' => 'Rp '.$this->formatCurrency((float) $row->amount),
                            'lines' => ['Attachments: '.(int) $row->attachment_count],
                        ],
                        [
                            'primary' => $row->category_name ?: '-',
                        ],
                        [
                            'primary' => $row->user_name,
                        ],
                    ],
                    'actions' => [
                        ['label' => 'Inspect', 'href' => route('internal.transactions.index', ['show' => $row->id]), 'variant' => 'secondary'],
                        ['label' => 'Tenant', 'href' => route('internal.tenants.show', $row->tenant_id), 'variant' => 'ghost'],
                    ],
                ])->all(),
            ],
            'detail' => $detail,
        ]);
    }

    private function showAttachments(Request $request): View
    {
        $rows = DB::table('attachments')
            ->join('tenants', 'tenants.id', '=', 'attachments.tenant_id')
            ->join('tenant_users', 'tenant_users.id', '=', 'attachments.uploaded_by_user_id')
            ->leftJoin('attachment_transaction', 'attachment_transaction.attachment_id', '=', 'attachments.id')
            ->groupBy([
                'attachments.id',
                'attachments.tenant_id',
                'attachments.storage_disk',
                'attachments.original_file_name',
                'attachments.mime_type',
                'attachments.file_size',
                'attachments.width',
                'attachments.height',
                'attachments.created_at',
                'tenants.name',
                'tenant_users.name',
            ])
            ->orderByDesc('attachments.created_at')
            ->limit(40)
            ->get([
                'attachments.id',
                'attachments.tenant_id',
                'attachments.storage_disk',
                'attachments.original_file_name',
                'attachments.mime_type',
                'attachments.file_size',
                'attachments.width',
                'attachments.height',
                'attachments.created_at',
                'tenants.name as tenant_name',
                'tenant_users.name as user_name',
                DB::raw('count(attachment_transaction.transaction_id) as transaction_count'),
            ]);

        $showId = (int) $request->query('show', 0);
        $detail = $showId > 0 ? $this->buildAttachmentDetail($showId) : null;

        return $this->renderResourceIndex($request, [
            'page' => [
                'title' => 'Attachments',
                'description' => 'Lihat proof image lintas tenant, ukuran file, preview route, dan relasi transaksi.',
                'eyebrow' => 'Internal Module',
            ],
            'toolbar' => [
                'search_label' => 'Cari attachment atau mime type',
                'search_placeholder' => 'Search attachment, tenant, or mime type',
            ],
            'summary' => [
                ['label' => 'Visible files', 'value' => (string) $rows->count(), 'note' => '40 attachment terbaru', 'tone' => 'neutral'],
                ['label' => 'Linked tx', 'value' => (string) $rows->filter(fn (object $row): bool => (int) $row->transaction_count > 0)->count(), 'note' => 'Sudah terhubung ke transaksi', 'tone' => 'neutral'],
                ['label' => 'Unlinked', 'value' => (string) $rows->filter(fn (object $row): bool => (int) $row->transaction_count === 0)->count(), 'note' => 'Perlu cek session/save flow', 'tone' => 'alert'],
                ['label' => 'Image payload', 'value' => $this->formatBytes((int) $rows->sum(fn (object $row): int => (int) $row->file_size)), 'note' => 'Total ukuran file pada daftar ini', 'tone' => 'neutral'],
            ],
            'table' => [
                'headers' => ['Tenant / File', 'Type', 'Size', 'Uploader', 'Linked tx', 'Action'],
                'rows' => $rows->map(fn (object $row): array => [
                    'cells' => [
                        [
                            'primary' => $row->tenant_name.' / '.($row->original_file_name ?: 'image'),
                            'lines' => ['Attachment #'.$row->id, 'Uploaded: '.$this->formatDateTime($row->created_at)],
                        ],
                        [
                            'primary' => $row->mime_type,
                            'lines' => [$row->width && $row->height ? $row->width.' x '.$row->height : 'Dimensions unknown'],
                        ],
                        [
                            'primary' => $this->formatBytes((int) $row->file_size),
                            'lines' => ['Disk: '.$row->storage_disk],
                        ],
                        [
                            'primary' => $row->user_name,
                        ],
                        [
                            'primary' => (string) $row->transaction_count,
                        ],
                    ],
                    'actions' => [
                        ['label' => 'Inspect', 'href' => route('internal.attachments.index', ['show' => $row->id]), 'variant' => 'secondary'],
                        ['label' => 'Tenant', 'href' => route('internal.tenants.show', $row->tenant_id), 'variant' => 'ghost'],
                    ],
                ])->all(),
            ],
            'detail' => $detail,
        ]);
    }

    private function showTenantAudit(Request $request): View
    {
        $rows = DB::table('audit_logs')
            ->join('tenants', 'tenants.id', '=', 'audit_logs.tenant_id')
            ->leftJoin('tenant_users', 'tenant_users.id', '=', 'audit_logs.actor_tenant_user_id')
            ->leftJoin('platform_admin_users', 'platform_admin_users.id', '=', 'audit_logs.actor_platform_admin_user_id')
            ->orderByDesc('audit_logs.created_at')
            ->limit(40)
            ->get([
                'audit_logs.id',
                'audit_logs.tenant_id',
                'audit_logs.actor_source',
                'audit_logs.entity_type',
                'audit_logs.entity_id',
                'audit_logs.action',
                'audit_logs.before_payload',
                'audit_logs.after_payload',
                'audit_logs.created_at',
                'tenants.name as tenant_name',
                'tenant_users.name as tenant_actor_name',
                'platform_admin_users.name as platform_actor_name',
            ]);

        $showId = (int) $request->query('show', 0);
        $detail = $showId > 0 ? $this->buildTenantAuditDetail($showId) : null;

        return $this->renderResourceIndex($request, [
            'page' => [
                'title' => 'Tenant Audit',
                'description' => 'Audit domain tenant-facing untuk edit transaksi, void, dan intervensi support.',
                'eyebrow' => 'Internal Module',
            ],
            'toolbar' => [
                'search_label' => 'Cari action atau entity tenant',
                'search_placeholder' => 'Search action, entity, or tenant',
            ],
            'summary' => [
                ['label' => 'Visible events', 'value' => (string) $rows->count(), 'note' => '40 log terbaru', 'tone' => 'neutral'],
                ['label' => 'Transaction update', 'value' => (string) $rows->where('action', 'transaction_updated')->count(), 'note' => 'Perubahan data transaksi', 'tone' => 'neutral'],
                ['label' => 'Transaction void', 'value' => (string) $rows->where('action', 'transaction_voided')->count(), 'note' => 'Pembatalan transaksi', 'tone' => 'alert'],
                ['label' => 'Support touch', 'value' => (string) $rows->where('actor_source', 'platform_admin')->count(), 'note' => 'Aksi internal ke domain tenant', 'tone' => 'alert'],
            ],
            'table' => [
                'headers' => ['Tenant / Event', 'Actor', 'Action', 'Entity', 'Time', 'Action'],
                'rows' => $rows->map(fn (object $row): array => [
                    'cells' => [
                        [
                            'primary' => $row->tenant_name.' / Audit #'.$row->id,
                            'lines' => ['Entity #'.$row->entity_id],
                        ],
                        [
                            'primary' => $row->tenant_actor_name ?: $row->platform_actor_name ?: strtoupper((string) $row->actor_source),
                            'lines' => ['Source: '.strtoupper((string) $row->actor_source)],
                        ],
                        [
                            'primary' => Str::of((string) $row->action)->replace('_', ' ')->title()->toString(),
                        ],
                        [
                            'primary' => Str::of((string) $row->entity_type)->replace('_', ' ')->title()->toString(),
                        ],
                        [
                            'primary' => $this->formatDateTime($row->created_at),
                        ],
                    ],
                    'actions' => [
                        ['label' => 'Inspect', 'href' => route('internal.audit.tenant-facing.index', ['show' => $row->id]), 'variant' => 'secondary'],
                        ['label' => 'Tenant', 'href' => route('internal.tenants.show', $row->tenant_id), 'variant' => 'ghost'],
                    ],
                ])->all(),
            ],
            'detail' => $detail,
        ]);
    }

    private function showPlatformAudit(Request $request): View
    {
        $rows = DB::table('platform_admin_audit_logs')
            ->join('platform_admin_users', 'platform_admin_users.id', '=', 'platform_admin_audit_logs.platform_admin_user_id')
            ->orderByDesc('platform_admin_audit_logs.created_at')
            ->limit(40)
            ->get([
                'platform_admin_audit_logs.id',
                'platform_admin_audit_logs.action',
                'platform_admin_audit_logs.target_entity_type',
                'platform_admin_audit_logs.target_entity_id',
                'platform_admin_audit_logs.reason_note',
                'platform_admin_audit_logs.before_snapshot',
                'platform_admin_audit_logs.after_snapshot',
                'platform_admin_audit_logs.created_at',
                'platform_admin_users.name as actor_name',
            ]);

        $showId = (int) $request->query('show', 0);
        $detail = $showId > 0 ? $this->buildPlatformAuditDetail($showId) : null;

        return $this->renderResourceIndex($request, [
            'page' => [
                'title' => 'Platform Audit',
                'description' => 'Jejak aksi sensitif super admin seperti verification support dan nanti WAHA control.',
                'eyebrow' => 'Internal Module',
            ],
            'toolbar' => [
                'search_label' => 'Cari action internal',
                'search_placeholder' => 'Search internal action or target',
            ],
            'summary' => [
                ['label' => 'Visible events', 'value' => (string) $rows->count(), 'note' => '40 log internal terbaru', 'tone' => 'neutral'],
                ['label' => 'Verification support', 'value' => (string) $rows->filter(fn (object $row): bool => str_contains((string) $row->action, 'verification'))->count(), 'note' => 'Resend dan regenerate code', 'tone' => 'neutral'],
                ['label' => 'WAHA reserved', 'value' => (string) $rows->filter(fn (object $row): bool => str_contains((string) $row->action, 'waha'))->count(), 'note' => 'Slot untuk action WAHA', 'tone' => 'alert'],
                ['label' => 'Today', 'value' => (string) $rows->filter(fn (object $row): bool => Carbon::parse((string) $row->created_at)->isToday())->count(), 'note' => 'Aktivitas hari ini', 'tone' => 'neutral'],
            ],
            'table' => [
                'headers' => ['Audit', 'Actor', 'Action', 'Target', 'Reason', 'Action'],
                'rows' => $rows->map(fn (object $row): array => [
                    'cells' => [
                        [
                            'primary' => 'Audit #'.$row->id,
                            'lines' => ['Created: '.$this->formatDateTime($row->created_at)],
                        ],
                        [
                            'primary' => $row->actor_name,
                        ],
                        [
                            'primary' => Str::of((string) $row->action)->replace('_', ' ')->title()->toString(),
                        ],
                        [
                            'primary' => Str::of((string) $row->target_entity_type)->replace('_', ' ')->title()->toString().' #'.$row->target_entity_id,
                        ],
                        [
                            'primary' => Str::limit((string) ($row->reason_note ?: '-'), 42),
                        ],
                    ],
                    'actions' => [
                        ['label' => 'Inspect', 'href' => route('internal.audit.platform.index', ['show' => $row->id]), 'variant' => 'secondary'],
                    ],
                ])->all(),
            ],
            'detail' => $detail,
        ]);
    }

    private function showWaha(Request $request): View
    {
        $rows = DB::table('bot_instances')
            ->leftJoin('tenant_bot_assignments', function ($join): void {
                $join->on('tenant_bot_assignments.bot_instance_id', '=', 'bot_instances.id')
                    ->whereNull('tenant_bot_assignments.unassigned_at');
            })
            ->groupBy([
                'bot_instances.id',
                'bot_instances.name',
                'bot_instances.waha_instance_key',
                'bot_instances.bot_whatsapp_number',
                'bot_instances.connection_status',
                'bot_instances.qr_status',
                'bot_instances.webhook_status',
                'bot_instances.last_heartbeat_at',
                'bot_instances.last_reconnect_at',
                'bot_instances.last_qr_refresh_at',
                'bot_instances.last_error_message',
                'bot_instances.is_default',
                'bot_instances.is_active',
                'bot_instances.meta_json',
            ])
            ->orderByDesc('bot_instances.is_default')
            ->orderBy('bot_instances.name')
            ->get([
                'bot_instances.id',
                'bot_instances.name',
                'bot_instances.waha_instance_key',
                'bot_instances.bot_whatsapp_number',
                'bot_instances.connection_status',
                'bot_instances.qr_status',
                'bot_instances.webhook_status',
                'bot_instances.last_heartbeat_at',
                'bot_instances.last_reconnect_at',
                'bot_instances.last_qr_refresh_at',
                'bot_instances.last_error_message',
                'bot_instances.is_default',
                'bot_instances.is_active',
                'bot_instances.meta_json',
                DB::raw('count(tenant_bot_assignments.id) as active_tenant_count'),
            ]);

        $showId = (int) $request->query('show', 0);
        $detail = $showId > 0 ? $this->buildWahaDetail($showId) : null;
        $healthCounts = $rows->map(fn (object $row): string => $this->deriveWahaHealth($row))->countBy();

        return $this->renderResourceIndex($request, [
            'page' => [
                'title' => 'WAHA',
                'description' => 'Lihat health bot instance, webhook, QR status, dan tenant assignment tanpa masuk server manual.',
                'eyebrow' => 'Internal Module',
            ],
            'toolbar' => [
                'search_label' => 'Pantau bot instance aktif',
                'search_placeholder' => 'WAHA overview',
            ],
            'summary' => [
                ['label' => 'Bot instances', 'value' => (string) $rows->count(), 'note' => 'Konsep future-ready multi bot', 'tone' => 'neutral'],
                ['label' => 'Healthy', 'value' => (string) ($healthCounts['healthy'] ?? 0), 'note' => 'Connected dan webhook sehat', 'tone' => 'neutral'],
                ['label' => 'Warning', 'value' => (string) ($healthCounts['warning'] ?? 0), 'note' => 'Perlu perhatian tapi belum critical', 'tone' => 'alert'],
                ['label' => 'Critical', 'value' => (string) ($healthCounts['critical'] ?? 0), 'note' => 'Bot/error/webhook putus', 'tone' => 'alert'],
            ],
            'table' => [
                'headers' => ['Bot instance', 'Health', 'QR / Webhook', 'Heartbeat', 'Assignments', 'Action'],
                'rows' => $rows->map(function (object $row): array {
                    $health = $this->deriveWahaHealth($row);

                    return [
                        'cells' => [
                            [
                                'primary' => $row->name,
                                'lines' => [
                                    $row->waha_instance_key,
                                    $row->bot_whatsapp_number ?: 'Nomor bot belum tersimpan',
                                ],
                                'badges' => array_values(array_filter([
                                    $row->is_default ? ['label' => 'default', 'tone' => 'neutral'] : null,
                                    $row->is_active ? ['label' => 'active', 'tone' => 'success'] : ['label' => 'inactive', 'tone' => 'warning'],
                                ])),
                            ],
                            [
                                'badges' => [
                                    ['label' => $health, 'tone' => $health === 'healthy' ? 'success' : 'warning'],
                                    ['label' => (string) $row->connection_status, 'tone' => $row->connection_status === 'connected' ? 'success' : 'warning'],
                                ],
                            ],
                            [
                                'primary' => 'QR: '.(string) $row->qr_status,
                                'lines' => ['Webhook: '.($row->webhook_status ?: 'unknown')],
                            ],
                            [
                                'primary' => $this->formatDateTime($row->last_heartbeat_at),
                                'lines' => [
                                    'Reconnect: '.$this->formatDateTime($row->last_reconnect_at),
                                    'QR refresh: '.$this->formatDateTime($row->last_qr_refresh_at),
                                ],
                            ],
                            [
                                'primary' => (string) $row->active_tenant_count,
                                'lines' => ['Tenant aktif'],
                            ],
                        ],
                        'actions' => [
                            ['label' => 'Inspect', 'href' => route('internal.waha.index', ['show' => $row->id]), 'variant' => 'secondary'],
                        ],
                    ];
                })->all(),
            ],
            'detail' => $detail,
            'emptyState' => [
                'title' => 'Belum ada bot instance',
                'description' => 'Seed atau sinkronisasi bot_instances dulu agar super admin bisa memonitor WAHA dari dashboard.',
            ],
        ]);
    }

    private function renderResourceIndex(Request $request, array $payload): View
    {
        return view('internal.resource-index', [
            ...$this->baseViewData($request),
            ...$payload,
        ]);
    }

    private function baseViewData(Request $request): array
    {
        return [
            'navigation' => PlatformAdminNavigation::items(),
            'authUser' => $request->user('platform_admin'),
        ];
    }

    private function buildUserDetail(int $userId): ?array
    {
        $user = DB::table('tenant_users')
            ->join('tenants', 'tenants.id', '=', 'tenant_users.tenant_id')
            ->leftJoin('activation_codes', function ($join): void {
                $join->on('activation_codes.tenant_user_id', '=', 'tenant_users.id')
                    ->where('activation_codes.active_lock', '=', 1);
            })
            ->where('tenant_users.id', $userId)
            ->first([
                'tenant_users.id',
                'tenant_users.tenant_id',
                'tenant_users.name',
                'tenant_users.role',
                'tenant_users.user_status',
                'tenant_users.verification_status',
                'tenant_users.whatsapp_number',
                'tenant_users.last_login_at',
                'tenant_users.created_at',
                'tenant_users.verified_at',
                'tenants.name as tenant_name',
                'activation_codes.code_last4',
                'activation_codes.expires_at as activation_expires_at',
            ]);

        if ($user === null) {
            return null;
        }

        $sessionCount = (int) DB::table('conversation_sessions')->where('tenant_user_id', $userId)->count();
        $transactionCount = (int) DB::table('transactions')->where('recorded_by_user_id', $userId)->count();

        return [
            'title' => $user->name,
            'subtitle' => $user->tenant_name,
            'badges' => [
                ['label' => strtoupper((string) $user->role), 'tone' => $user->role === 'owner' ? 'neutral' : 'success'],
                ['label' => (string) $user->user_status, 'tone' => $user->user_status === 'active' ? 'success' : 'warning'],
                ['label' => (string) $user->verification_status, 'tone' => $user->verification_status === 'verified' ? 'success' : 'warning'],
            ],
            'can_manage_verification' => $user->verification_status === 'pending_verification',
            'tenant_href' => route('internal.tenants.show', $user->tenant_id),
            'sections' => [
                [
                    'title' => 'Identity',
                    'lines' => [
                        'WhatsApp: '.$user->whatsapp_number,
                        'User status: '.$user->user_status,
                        'Tenant: '.$user->tenant_name,
                    ],
                ],
                [
                    'title' => 'Verification',
                    'lines' => [
                        'Code: '.($user->code_last4 ? 'KAS-'.$user->code_last4 : 'No active code'),
                        'Code expires: '.$this->formatDateTime($user->activation_expires_at),
                        'Verified at: '.$this->formatDateTime($user->verified_at),
                    ],
                ],
                [
                    'title' => 'Activity',
                    'lines' => [
                        'Last login: '.$this->formatDateTime($user->last_login_at),
                        'Sessions: '.$sessionCount,
                        'Transactions: '.$transactionCount,
                    ],
                ],
            ],
        ];
    }

    private function buildSessionDetail(int $sessionId): ?array
    {
        $session = DB::table('conversation_sessions')
            ->join('tenant_users', 'tenant_users.id', '=', 'conversation_sessions.tenant_user_id')
            ->join('tenants', 'tenants.id', '=', 'conversation_sessions.tenant_id')
            ->where('conversation_sessions.id', $sessionId)
            ->first([
                'conversation_sessions.id',
                'conversation_sessions.tenant_id',
                'conversation_sessions.status',
                'conversation_sessions.current_state',
                'conversation_sessions.intent_type',
                'conversation_sessions.source_message_id',
                'conversation_sessions.last_message_at',
                'conversation_sessions.expires_at',
                'conversation_sessions.completed_at',
                'conversation_sessions.cancelled_at',
                'conversation_sessions.expired_at',
                'conversation_sessions.draft_payload',
                'tenant_users.name as user_name',
                'tenants.name as tenant_name',
            ]);

        if ($session === null) {
            return null;
        }

        return [
            'title' => $session->tenant_name.' / Session #'.$session->id,
            'badges' => [
                ['label' => (string) $session->status, 'tone' => $session->status === 'active' ? 'success' : 'warning'],
                ['label' => $session->current_state, 'tone' => 'neutral'],
            ],
            'sections' => [
                [
                    'title' => 'Flow state',
                    'lines' => [
                        'User: '.$session->user_name,
                        'Intent: '.($session->intent_type ?: '-'),
                        'Source message: '.($session->source_message_id ?: '-'),
                    ],
                ],
                [
                    'title' => 'Timestamps',
                    'lines' => [
                        'Last message: '.$this->formatDateTime($session->last_message_at),
                        'Expires: '.$this->formatDateTime($session->expires_at),
                        'Completed: '.$this->formatDateTime($session->completed_at),
                        'Cancelled: '.$this->formatDateTime($session->cancelled_at),
                        'Expired: '.$this->formatDateTime($session->expired_at),
                    ],
                    'links' => [
                        ['label' => 'Open tenant detail', 'href' => route('internal.tenants.show', $session->tenant_id)],
                    ],
                ],
                [
                    'title' => 'Draft payload',
                    'code' => $this->prettyJson($session->draft_payload),
                ],
            ],
        ];
    }

    private function buildTransactionDetail(int $transactionId): ?array
    {
        $transaction = DB::table('transactions')
            ->join('tenants', 'tenants.id', '=', 'transactions.tenant_id')
            ->join('tenant_users', 'tenant_users.id', '=', 'transactions.recorded_by_user_id')
            ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
            ->leftJoin('accounts as source_accounts', 'source_accounts.id', '=', 'transactions.source_account_id')
            ->leftJoin('accounts as destination_accounts', 'destination_accounts.id', '=', 'transactions.destination_account_id')
            ->where('transactions.id', $transactionId)
            ->first([
                'transactions.id',
                'transactions.tenant_id',
                'transactions.type',
                'transactions.amount',
                'transactions.status',
                'transactions.description',
                'transactions.transaction_date',
                'transactions.voided_at',
                'transactions.void_reason',
                'transactions.created_at',
                'tenants.name as tenant_name',
                'tenant_users.name as user_name',
                'categories.name as category_name',
                'source_accounts.name as source_account_name',
                'destination_accounts.name as destination_account_name',
            ]);

        if ($transaction === null) {
            return null;
        }

        $attachmentLinks = DB::table('attachment_transaction')
            ->join('attachments', 'attachments.id', '=', 'attachment_transaction.attachment_id')
            ->where('attachment_transaction.transaction_id', $transactionId)
            ->orderByDesc('attachments.created_at')
            ->get([
                'attachments.id',
                'attachments.original_file_name',
                'attachments.file_size',
            ])
            ->map(fn (object $row): array => [
                'label' => ($row->original_file_name ?: 'Attachment').' ('.$this->formatBytes((int) $row->file_size).')',
                'href' => route('internal.attachments.preview', $row->id),
            ])
            ->all();

        return [
            'title' => $transaction->tenant_name.' / Tx #'.$transaction->id,
            'badges' => [
                ['label' => strtoupper((string) $transaction->type), 'tone' => $transaction->type === 'expense' ? 'warning' : 'success'],
                ['label' => (string) $transaction->status, 'tone' => $transaction->status === 'completed' ? 'success' : 'warning'],
            ],
            'sections' => [
                [
                    'title' => 'Transaction',
                    'lines' => [
                        'Amount: Rp '.$this->formatCurrency((float) $transaction->amount),
                        'Date: '.$this->formatDate($transaction->transaction_date),
                        'Category: '.($transaction->category_name ?: '-'),
                        'Recorder: '.$transaction->user_name,
                    ],
                ],
                [
                    'title' => 'Accounts',
                    'lines' => [
                        'Source: '.($transaction->source_account_name ?: '-'),
                        'Destination: '.($transaction->destination_account_name ?: '-'),
                        'Description: '.($transaction->description ?: '-'),
                    ],
                ],
                [
                    'title' => 'Audit flags',
                    'lines' => [
                        'Created: '.$this->formatDateTime($transaction->created_at),
                        'Voided at: '.$this->formatDateTime($transaction->voided_at),
                        'Void reason: '.($transaction->void_reason ?: '-'),
                    ],
                    'links' => array_merge(
                        [['label' => 'Open tenant detail', 'href' => route('internal.tenants.show', $transaction->tenant_id)]],
                        $attachmentLinks
                    ),
                ],
            ],
        ];
    }

    private function buildAttachmentDetail(int $attachmentId): ?array
    {
        $attachment = DB::table('attachments')
            ->join('tenants', 'tenants.id', '=', 'attachments.tenant_id')
            ->join('tenant_users', 'tenant_users.id', '=', 'attachments.uploaded_by_user_id')
            ->where('attachments.id', $attachmentId)
            ->first([
                'attachments.id',
                'attachments.tenant_id',
                'attachments.original_file_name',
                'attachments.mime_type',
                'attachments.file_size',
                'attachments.width',
                'attachments.height',
                'attachments.storage_disk',
                'attachments.storage_path',
                'attachments.source_message_id',
                'attachments.created_at',
                'tenants.name as tenant_name',
                'tenant_users.name as user_name',
            ]);

        if ($attachment === null) {
            return null;
        }

        $transactionLinks = DB::table('attachment_transaction')
            ->join('transactions', 'transactions.id', '=', 'attachment_transaction.transaction_id')
            ->where('attachment_transaction.attachment_id', $attachmentId)
            ->orderByDesc('transactions.transaction_date')
            ->get(['transactions.id', 'transactions.type', 'transactions.amount'])
            ->map(fn (object $row): array => [
                'label' => 'Tx #'.$row->id.' · '.strtoupper((string) $row->type).' · Rp '.$this->formatCurrency((float) $row->amount),
                'href' => route('internal.transactions.index', ['show' => $row->id]),
            ])
            ->all();

        return [
            'title' => $attachment->tenant_name.' / Attachment #'.$attachment->id,
            'badges' => [
                ['label' => $attachment->mime_type, 'tone' => 'neutral'],
            ],
            'sections' => [
                [
                    'title' => 'File',
                    'lines' => [
                        'Name: '.($attachment->original_file_name ?: 'image'),
                        'Size: '.$this->formatBytes((int) $attachment->file_size),
                        'Dimensions: '.($attachment->width && $attachment->height ? $attachment->width.' x '.$attachment->height : '-'),
                        'Uploaded by: '.$attachment->user_name,
                    ],
                ],
                [
                    'title' => 'Storage',
                    'lines' => [
                        'Disk: '.$attachment->storage_disk,
                        'Path: '.$attachment->storage_path,
                        'Source message: '.$attachment->source_message_id,
                        'Created: '.$this->formatDateTime($attachment->created_at),
                    ],
                    'links' => array_merge(
                        [['label' => 'Preview image', 'href' => route('internal.attachments.preview', $attachment->id)]],
                        $transactionLinks
                    ),
                ],
            ],
        ];
    }

    private function buildTenantAuditDetail(int $auditId): ?array
    {
        $audit = DB::table('audit_logs')
            ->join('tenants', 'tenants.id', '=', 'audit_logs.tenant_id')
            ->leftJoin('tenant_users', 'tenant_users.id', '=', 'audit_logs.actor_tenant_user_id')
            ->leftJoin('platform_admin_users', 'platform_admin_users.id', '=', 'audit_logs.actor_platform_admin_user_id')
            ->where('audit_logs.id', $auditId)
            ->first([
                'audit_logs.id',
                'audit_logs.tenant_id',
                'audit_logs.actor_source',
                'audit_logs.entity_type',
                'audit_logs.entity_id',
                'audit_logs.action',
                'audit_logs.before_payload',
                'audit_logs.after_payload',
                'audit_logs.created_at',
                'tenants.name as tenant_name',
                'tenant_users.name as tenant_actor_name',
                'platform_admin_users.name as platform_actor_name',
            ]);

        if ($audit === null) {
            return null;
        }

        return [
            'title' => $audit->tenant_name.' / Audit #'.$audit->id,
            'badges' => [
                ['label' => strtoupper((string) $audit->actor_source), 'tone' => $audit->actor_source === 'platform_admin' ? 'warning' : 'neutral'],
            ],
            'sections' => [
                [
                    'title' => 'Event',
                    'lines' => [
                        'Actor: '.($audit->tenant_actor_name ?: $audit->platform_actor_name ?: '-'),
                        'Action: '.Str::of((string) $audit->action)->replace('_', ' ')->title()->toString(),
                        'Entity: '.Str::of((string) $audit->entity_type)->replace('_', ' ')->title()->toString().' #'.$audit->entity_id,
                        'Time: '.$this->formatDateTime($audit->created_at),
                    ],
                    'links' => [
                        ['label' => 'Open tenant detail', 'href' => route('internal.tenants.show', $audit->tenant_id)],
                    ],
                ],
                [
                    'title' => 'Before snapshot',
                    'code' => $this->prettyJson($audit->before_payload),
                ],
                [
                    'title' => 'After snapshot',
                    'code' => $this->prettyJson($audit->after_payload),
                ],
            ],
        ];
    }

    private function buildPlatformAuditDetail(int $auditId): ?array
    {
        $audit = DB::table('platform_admin_audit_logs')
            ->join('platform_admin_users', 'platform_admin_users.id', '=', 'platform_admin_audit_logs.platform_admin_user_id')
            ->where('platform_admin_audit_logs.id', $auditId)
            ->first([
                'platform_admin_audit_logs.id',
                'platform_admin_audit_logs.action',
                'platform_admin_audit_logs.target_entity_type',
                'platform_admin_audit_logs.target_entity_id',
                'platform_admin_audit_logs.reason_note',
                'platform_admin_audit_logs.before_snapshot',
                'platform_admin_audit_logs.after_snapshot',
                'platform_admin_audit_logs.created_at',
                'platform_admin_users.name as actor_name',
            ]);

        if ($audit === null) {
            return null;
        }

        return [
            'title' => 'Platform Audit #'.$audit->id,
            'badges' => [
                ['label' => Str::of((string) $audit->action)->replace('_', ' ')->title()->toString(), 'tone' => 'neutral'],
            ],
            'sections' => [
                [
                    'title' => 'Event',
                    'lines' => [
                        'Actor: '.$audit->actor_name,
                        'Target: '.Str::of((string) $audit->target_entity_type)->replace('_', ' ')->title()->toString().' #'.$audit->target_entity_id,
                        'Reason: '.($audit->reason_note ?: '-'),
                        'Time: '.$this->formatDateTime($audit->created_at),
                    ],
                ],
                [
                    'title' => 'Before snapshot',
                    'code' => $this->prettyJson($audit->before_snapshot),
                ],
                [
                    'title' => 'After snapshot',
                    'code' => $this->prettyJson($audit->after_snapshot),
                ],
            ],
        ];
    }

    private function buildWahaDetail(int $botInstanceId): ?array
    {
        $bot = DB::table('bot_instances')
            ->where('id', $botInstanceId)
            ->first();

        if ($bot === null) {
            return null;
        }

        $assignedTenants = DB::table('tenant_bot_assignments')
            ->join('tenants', 'tenants.id', '=', 'tenant_bot_assignments.tenant_id')
            ->where('tenant_bot_assignments.bot_instance_id', $botInstanceId)
            ->whereNull('tenant_bot_assignments.unassigned_at')
            ->orderBy('tenants.name')
            ->get(['tenants.id', 'tenants.name'])
            ->map(fn (object $row): array => [
                'label' => $row->name,
                'href' => route('internal.tenants.show', $row->id),
            ])
            ->all();

        return [
            'title' => $bot->name,
            'badges' => [
                ['label' => $this->deriveWahaHealth($bot), 'tone' => $this->deriveWahaHealth($bot) === 'healthy' ? 'success' : 'warning'],
                ['label' => (string) $bot->connection_status, 'tone' => $bot->connection_status === 'connected' ? 'success' : 'warning'],
            ],
            'sections' => [
                [
                    'title' => 'Bot status',
                    'lines' => [
                        'Instance key: '.$bot->waha_instance_key,
                        'Bot number: '.($bot->bot_whatsapp_number ?: '-'),
                        'QR status: '.$bot->qr_status,
                        'Webhook status: '.($bot->webhook_status ?: 'unknown'),
                    ],
                ],
                [
                    'title' => 'Timestamps',
                    'lines' => [
                        'Heartbeat: '.$this->formatDateTime($bot->last_heartbeat_at),
                        'Reconnect: '.$this->formatDateTime($bot->last_reconnect_at),
                        'QR refresh: '.$this->formatDateTime($bot->last_qr_refresh_at),
                        'Last error: '.($bot->last_error_message ?: '-'),
                    ],
                    'links' => $assignedTenants,
                    'forms' => [
                        [
                            'label' => 'Reconnect WAHA',
                            'action' => route('internal.waha.reconnect', $bot->id),
                            'variant' => 'secondary',
                        ],
                        [
                            'label' => 'Refresh QR',
                            'action' => route('internal.waha.refresh-qr', $bot->id),
                            'variant' => 'ghost',
                        ],
                    ],
                ],
                [
                    'title' => 'Meta payload',
                    'code' => $this->prettyJson($this->metaForDisplay($bot->meta_json)),
                ],
                [
                    'title' => 'Latest QR',
                    'image' => data_get($this->decodeMeta($bot->meta_json), 'latest_qr.data_url'),
                ],
            ],
        ];
    }

    private function deriveWahaHealth(object $bot): string
    {
        if ((string) $bot->connection_status === 'connected') {
            if ($bot->webhook_status !== null && (string) $bot->webhook_status !== 'healthy') {
                return 'warning';
            }

            if ($bot->last_heartbeat_at === null) {
                return 'warning';
            }

            $heartbeat = Carbon::parse((string) $bot->last_heartbeat_at);

            if ($heartbeat->lt(now()->subMinutes(5))) {
                return 'critical';
            }

            if ($heartbeat->lt(now()->subMinutes(2))) {
                return 'warning';
            }

            return 'healthy';
        }

        if (in_array((string) $bot->connection_status, ['disconnected', 'error'], true)) {
            return 'critical';
        }

        return 'warning';
    }

    private function prettyJson(mixed $payload): string
    {
        if ($payload === null || $payload === '') {
            return "No payload";
        }

        $decoded = is_string($payload) ? json_decode($payload, true) : $payload;

        if ($decoded === null && is_string($payload) && trim($payload) !== 'null') {
            return (string) $payload;
        }

        return (string) json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function metaForDisplay(mixed $payload): array|string
    {
        $meta = $this->decodeMeta($payload);

        if ($meta === []) {
            return 'No payload';
        }

        if (isset($meta['latest_qr']['data_url'])) {
            $meta['latest_qr']['data_url'] = '[omitted: rendered below as image]';
        }

        return $meta;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeMeta(mixed $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (! is_string($payload) || trim($payload) === '') {
            return [];
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function formatDateTime(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return Carbon::parse((string) $value)->format('d M Y H:i');
    }

    private function formatDate(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return Carbon::parse((string) $value)->format('d M Y');
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return number_format($bytes / (1024 * 1024), 1).' MB';
    }

    private function formatCurrency(float $amount): string
    {
        return number_format($amount, 0, ',', '.');
    }
}
