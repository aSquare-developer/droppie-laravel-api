@if (session('success'))
    <div class="flash flash-success mb-4">
        <span>{{ session('success') }}</span>
        <button type="button" aria-label="Close message" data-action="clear-flash">x</button>
    </div>
@endif

@if ($errors->any())
    <div class="flash flash-error mb-4">
        <div>
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
        <button type="button" aria-label="Close message" data-action="clear-flash">x</button>
    </div>
@endif
