
<!-- Header Penyedia -->
<div class="header">
    <div class="logo-container">
        <img src="Vicon.png" alt="Logo Vooking" class="logo-img" />
        <span class="logo-text">VOOKING</span>
    </div>
    <div class="icons">
        <div class="chat-icon-wrapper">
            <a href="chat_list.php" title="Chat Masuk">
                <i class="fa-solid fa-comments"></i>
            </a>
            <?php if ($hasNewChat): ?>
                <span class="chat-badge"></span>
            <?php endif; ?>
        </div>
        <div class="notif-icon-wrapper">
            <a href="semua_notifikasi.php" style="position: relative; color: inherit; text-decoration: none;">
                <i class="fa-solid fa-bell"></i>
                <?php if ($hasNewNotif): ?>
                    <span class="notif-badge"></span>
                <?php endif; ?>
            </a>
        </div>
        <a href="profil_penyedia.php"><i class="fa-solid fa-user" id="profile-icon"></i></a>
    </div>
</div>