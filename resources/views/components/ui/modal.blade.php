@props([
    'id',
    'title' => 'Detail',
])

<div class="modal" data-modal="{{ $id }}">
    <div class="modal-panel">
        <div class="panel-head">
            <div>
                <h2 class="panel-title">{{ $title }}</h2>
            </div>
            <button class="button button-ghost" type="button" data-modal-close>Tutup</button>
        </div>

        <p class="panel-copy">{{ $slot }}</p>
    </div>
</div>
