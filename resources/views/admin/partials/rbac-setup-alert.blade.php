@if(! \App\Support\AdminRbac::ready())
    <div class="alert alert-warning">
        <strong>Admin setup incomplete.</strong>
        Run migrations and seed roles on the server:
        <code>clear-cache.php?key=YOUR_SECRET&amp;migrate=1</code>
        then
        <code>seed-roles.php?key=YOUR_SECRET</code>.
        Until then, legacy admin access applies and some menu items may be limited.
    </div>
@endif
