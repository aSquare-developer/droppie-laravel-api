@if (session('success'))
    <div class="flash flash-success">
        <span>{{ session('success') }}</span>
        <button type="button" aria-label="Close message" data-action="clear-flash">x</button>
    </div>
@endif

@if ($errors->any())
    <div class="flash flash-error">
        <div>
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
        <button type="button" aria-label="Close message" data-action="clear-flash">x</button>
    </div>
@endif
