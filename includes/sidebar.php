<aside class="sidebar collapsed" id="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <span class="material-icons-round">folder_special</span>
            <span class="logo-text">Finder</span>
        </div>
        <button id="toggleSidebar" class="icon-btn-ghost">
            <span class="material-icons-round">menu</span>
        </button>
    </div>
    
    <nav>
        <a href="?page=dashboard" class="nav-item <?= $page == 'dashboard' ? 'active' : '' ?>">
            <span class="material-icons-round">dashboard</span>
            <span class="link-text">Обзор</span>
        </a>
        <a href="?page=history" class="nav-item <?= $page == 'history' ? 'active' : '' ?>">
            <span class="material-icons-round">history</span>
            <span class="link-text">История</span>
        </a>
        <a href="?page=clients" class="nav-item <?= $page == 'clients' ? 'active' : '' ?>">
            <span class="material-icons-round">people</span>
            <span class="link-text">Клиенты</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="?page=admin" class="nav-item admin-btn <?= $page == 'admin' ? 'active' : '' ?>">
            <span class="material-icons-round">admin_panel_settings</span>
            <span class="link-text">Админ</span>
        </a>
    </div>
</aside>