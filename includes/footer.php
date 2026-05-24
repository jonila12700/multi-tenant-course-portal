<?php if (isset($_SESSION['user_id'], $_SESSION['tenant_id'], $_SESSION['role']) && function_exists('asset_path')): ?>
<section
    class="live-center"
    data-realtime-widget
    data-base-path="<?php echo htmlspecialchars(app_base_path()); ?>"
    data-csrf-token="<?php echo htmlspecialchars(csrf_token()); ?>"
>
    <div class="live-header">
        <div>
            <span id="liveStatusDot" class="live-dot"></span>
            <strong>Live Center</strong>
        </div>
        <small>auto updates</small>
    </div>

    <div class="live-grid">
        <div class="live-panel">
            <h6>Online Users</h6>
            <ul id="liveOnlineUsers" class="live-list">
                <li class="live-empty">Loading...</li>
            </ul>
        </div>

        <div class="live-panel">
            <h6>Notifications</h6>
            <ul id="liveNotifications" class="live-list">
                <li class="live-empty">Loading...</li>
            </ul>
        </div>

        <div class="live-panel">
            <h6>Activity</h6>
            <ul id="liveActivity" class="live-list">
                <li class="live-empty">Loading...</li>
            </ul>
        </div>

        <div class="live-panel">
            <h6>Live Chat</h6>
            <ul id="liveMessages" class="live-messages">
                <li class="live-empty">Loading...</li>
            </ul>

            <form id="liveMessageForm" class="live-message-form">
                <input id="liveMessageInput" type="text" maxlength="500" placeholder="Message class..." autocomplete="off">
                <button type="submit">Send</button>
            </form>
        </div>
    </div>
</section>
<script src="<?php echo htmlspecialchars(asset_path('assets/js/realtime.js')); ?>"></script>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
