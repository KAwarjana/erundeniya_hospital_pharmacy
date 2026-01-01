// admin_notifications.js - Integrated with Material Dashboard
class AdminNotifications {
    constructor() {
        this.apiUrl = 'get_notifications.php';
        this.notifications = [];
        this.unreadCount = 0;
        this.isDropdownOpen = false;
        this.refreshInterval = null;
        this.init();
    }

    init() {
        this.loadNotifications();
        this.setupAutoRefresh();
        this.listenForNewAppointments();
        this.bindEvents();
    }

    bindEvents() {
        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            const notificationArea = e.target.closest('.nav-item.dropdown');
            if (!notificationArea && this.isDropdownOpen) {
                this.closeDropdown();
            }
        });

        // Handle notification item clicks
        document.addEventListener('click', (e) => {
            if (e.target.closest('.notification-item')) {
                const notificationId = e.target.closest('.notification-item').getAttribute('data-id');
                if (notificationId) {
                    this.markAsRead(notificationId);
                }
            }
        });
    }

    async loadNotifications() {
        try {
            const response = await fetch(`${this.apiUrl}?action=get_notifications&limit=10`);
            const data = await response.json();
            
            if (data.success) {
                this.notifications = data.notifications || [];
                this.unreadCount = data.unread_count || 0;
                this.updateNotificationBadge();
                this.renderNotificationsInDropdown();
            } else {
                console.error('Failed to load notifications:', data.message);
            }
        } catch (error) {
            console.error('Error loading notifications:', error);
        }
    }

    updateNotificationBadge() {
        const badge = document.getElementById('notificationCount');
        if (badge) {
            badge.textContent = this.unreadCount;
            badge.style.display = this.unreadCount > 0 ? 'flex' : 'none';
            
            // Add pulse animation for new notifications
            if (this.unreadCount > 0) {
                badge.style.animation = 'pulse 2s infinite';
            } else {
                badge.style.animation = 'none';
            }
        }
    }

    renderNotificationsInDropdown() {
        const dropdown = document.getElementById('notificationDropdown');
        const notificationsList = document.getElementById('notificationsList');
        
        if (!dropdown || !notificationsList) {
            console.warn('Notification dropdown elements not found');
            return;
        }

        // Clear existing content
        notificationsList.innerHTML = '';

        if (this.notifications.length === 0) {
            notificationsList.innerHTML = `
                <div style="padding: 20px; text-align: center; color: #666;">
                    <div style="font-size: 2rem; margin-bottom: 10px;">🔔</div>
                    <p style="margin: 0; font-size: 14px;">No notifications</p>
                </div>
            `;
            return;
        }

        // Add header with mark all read button
        const header = document.createElement('div');
        header.className = 'dropdown-header d-flex justify-content-between align-items-center';
        header.style.cssText = 'padding: 10px 15px; border-bottom: 1px solid #eee; background: #f8f9fa;';
        header.innerHTML = `
            <span style="font-weight: bold; color: #333;">Notifications (${this.unreadCount})</span>
            ${this.unreadCount > 0 ? `<button onclick="adminNotifications.markAllAsRead()" style="background: none; border: none; color: #007bff; font-size: 12px; cursor: pointer;">Mark all read</button>` : ''}
        `;
        notificationsList.appendChild(header);

        // Add notifications
        this.notifications.slice(0, 8).forEach(notification => {
            const item = this.createNotificationItem(notification);
            notificationsList.appendChild(item);
        });

        // Add footer with view all link
        const footer = document.createElement('div');
        footer.className = 'dropdown-footer';
        footer.style.cssText = 'padding: 10px; text-align: center; border-top: 1px solid #eee; background: #f8f9fa;';
        footer.innerHTML = `
            <a href="#" onclick="adminNotifications.showAllNotifications()" style="font-size: 12px; color: #007bff; text-decoration: none;">
                View all notifications
            </a>
        `;
        notificationsList.appendChild(footer);
    }

    createNotificationItem(notification) {
        const item = document.createElement('div');
        item.className = `notification-item ${!notification.is_read ? 'unread' : ''}`;
        item.setAttribute('data-id', notification.id);
        item.style.cssText = `
            padding: 12px 15px; 
            border-bottom: 1px solid #eee; 
            cursor: pointer; 
            transition: background-color 0.2s;
            ${!notification.is_read ? 'background-color: #fff3cd; border-left: 3px solid #ffc107;' : ''}
        `;

        item.innerHTML = `
            <div style="display: flex; align-items: flex-start; gap: 10px;">
                <div style="font-size: 16px; margin-top: 2px;">${notification.icon}</div>
                <div style="flex: 1; min-width: 0;">
                    <div style="font-weight: ${!notification.is_read ? 'bold' : 'normal'}; color: #333; font-size: 13px; margin-bottom: 4px;">
                        ${this.truncateText(notification.title, 40)}
                    </div>
                    <div style="color: #666; font-size: 12px; line-height: 1.3; margin-bottom: 4px;">
                        ${this.truncateText(notification.message, 60)}
                    </div>
                    <div style="color: #999; font-size: 11px;">
                        ${notification.time_ago}
                    </div>
                </div>
                ${!notification.is_read ? '<div style="width: 6px; height: 6px; background: #007bff; border-radius: 50%; margin-top: 6px;"></div>' : ''}
            </div>
        `;

        // Add hover effect
        item.addEventListener('mouseenter', () => {
            item.style.backgroundColor = notification.is_read ? '#f8f9fa' : '#fff8dc';
        });

        item.addEventListener('mouseleave', () => {
            item.style.backgroundColor = notification.is_read ? 'transparent' : '#fff3cd';
        });

        return item;
    }

    truncateText(text, maxLength) {
        return text.length > maxLength ? text.substring(0, maxLength) + '...' : text;
    }

    async markAsRead(notificationId) {
        try {
            const formData = new FormData();
            formData.append('action', 'mark_read');
            formData.append('notification_id', notificationId);

            const response = await fetch(this.apiUrl, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            
            if (data.success) {
                // Update local state
                const notification = this.notifications.find(n => n.id == notificationId);
                if (notification && !notification.is_read) {
                    notification.is_read = true;
                    this.unreadCount = Math.max(0, this.unreadCount - 1);
                    this.updateNotificationBadge();
                    this.renderNotificationsInDropdown();
                }
            }
        } catch (error) {
            console.error('Error marking notification as read:', error);
        }
    }

    async markAllAsRead() {
        try {
            const formData = new FormData();
            formData.append('action', 'mark_all_read');

            const response = await fetch(this.apiUrl, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            
            if (data.success) {
                // Update local state
                this.notifications.forEach(n => n.is_read = true);
                this.unreadCount = 0;
                this.updateNotificationBadge();
                this.renderNotificationsInDropdown();
            }
        } catch (error) {
            console.error('Error marking all notifications as read:', error);
        }
    }

    setupAutoRefresh() {
        // Refresh notifications every 30 seconds
        this.refreshInterval = setInterval(() => {
            this.loadNotifications();
        }, 30000);

        // Also refresh when page becomes visible again
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                this.loadNotifications();
            }
        });
    }

    listenForNewAppointments() {
        // Listen for custom event from appointment booking system
        window.addEventListener('newAppointmentBooked', () => {
            // Wait a moment for the notification to be created in database
            setTimeout(() => {
                this.loadNotifications();
                this.showNewNotificationAlert();
            }, 2000);
        });
    }

    showNewNotificationAlert() {
        // Create a subtle notification alert
        const alert = document.createElement('div');
        alert.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #28a745;
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 9999;
            font-size: 14px;
            opacity: 0;
            transform: translateY(-20px);
            transition: all 0.3s ease;
        `;
        alert.innerHTML = `
            <div style="display: flex; align-items: center; gap: 10px;">
                <span>🔔</span>
                <span>New appointment notification received!</span>
            </div>
        `;

        document.body.appendChild(alert);

        // Animate in
        setTimeout(() => {
            alert.style.opacity = '1';
            alert.style.transform = 'translateY(0)';
        }, 100);

        // Animate out and remove
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-20px)';
            setTimeout(() => alert.remove(), 300);
        }, 4000);
    }

    showAllNotifications() {
        // Create modal to show all notifications
        const modal = document.createElement('div');
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 10000;
            display: flex;
            justify-content: center;
            align-items: center;
        `;

        modal.innerHTML = `
            <div style="background: white; border-radius: 12px; width: 90%; max-width: 600px; max-height: 80%; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
                <div style="padding: 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                    <h5 style="margin: 0; color: #333;">All Notifications</h5>
                    <button onclick="this.closest('.modal-overlay').remove()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #999;">&times;</button>
                </div>
                <div style="max-height: 500px; overflow-y: auto; padding: 0;">
                    ${this.renderAllNotifications()}
                </div>
            </div>
        `;

        modal.className = 'modal-overlay';
        document.body.appendChild(modal);

        // Close on outside click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.remove();
            }
        });
    }

    renderAllNotifications() {
        if (this.notifications.length === 0) {
            return '<div style="padding: 40px; text-align: center; color: #666;">No notifications found</div>';
        }

        return this.notifications.map(notification => `
            <div class="notification-item ${!notification.is_read ? 'unread' : ''}" 
                 data-id="${notification.id}"
                 style="padding: 15px 20px; border-bottom: 1px solid #f0f0f0; cursor: pointer; ${!notification.is_read ? 'background-color: #fff3cd; border-left: 3px solid #ffc107;' : ''}"
                 onclick="adminNotifications.markAsRead(${notification.id})">
                <div style="display: flex; align-items: flex-start; gap: 12px;">
                    <div style="font-size: 18px; margin-top: 2px;">${notification.icon}</div>
                    <div style="flex: 1;">
                        <div style="font-weight: ${!notification.is_read ? 'bold' : 'normal'}; color: #333; margin-bottom: 6px;">
                            ${notification.title}
                        </div>
                        <div style="color: #666; font-size: 14px; line-height: 1.4; margin-bottom: 8px;">
                            ${notification.message}
                        </div>
                        <div style="color: #999; font-size: 12px;">
                            ${notification.time_ago} • ${notification.formatted_date}
                        </div>
                    </div>
                    ${!notification.is_read ? '<div style="width: 8px; height: 8px; background: #007bff; border-radius: 50%; margin-top: 8px;"></div>' : ''}
                </div>
            </div>
        `).join('');
    }

    closeDropdown() {
        const dropdown = document.getElementById('notificationDropdown');
        if (dropdown) {
            dropdown.style.display = 'none';
            this.isDropdownOpen = false;
        }
    }

    destroy() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
        }
    }
}

// Global function to toggle notifications dropdown
function toggleNotifications() {
    const dropdown = document.getElementById('notificationDropdown');
    const isVisible = dropdown.style.display === 'block';
    
    if (isVisible) {
        dropdown.style.display = 'none';
        window.adminNotifications.isDropdownOpen = false;
    } else {
        // Position the dropdown
        dropdown.style.cssText = `
            display: block !important;
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            width: 350px;
            max-height: 500px;
            overflow-y: auto;
            z-index: 1000;
        `;
        window.adminNotifications.isDropdownOpen = true;
        
        // Refresh notifications when opening
        window.adminNotifications.loadNotifications();
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.adminNotifications = new AdminNotifications();
});

// Clean up when page unloads
window.addEventListener('beforeunload', function() {
    if (window.adminNotifications) {
        window.adminNotifications.destroy();
    }
});