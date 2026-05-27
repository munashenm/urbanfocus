@if(!empty($blogMigrationNeeded))
<div class="alert alert-warning mb-4">
    <strong>Blog database upgrade required.</strong>
    Run migrations to enable categories, authors, tags, FAQs and content strategy tools.
    <div class="small mt-2">
        Visit <code>clear-cache.php?key=YOUR_SECRET&amp;migrate=1</code> on the server, then refresh this page.
    </div>
    @if(!empty($blogMigrationMissing))
        <ul class="small mb-0 mt-2">
            @foreach($blogMigrationMissing as $item)
                <li>Missing: {{ $item }}</li>
            @endforeach
        </ul>
    @endif
</div>
@endif
