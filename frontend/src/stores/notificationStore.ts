import { create } from 'zustand';
import { immer } from 'zustand/middleware/immer';

export type NotificationType = 'success' | 'error' | 'warning' | 'info';

export interface Notification {
    id: string;
    type: NotificationType;
    title: string;
    message?: string;
    duration?: number;
    action?: {
        label: string;
        onClick: () => void;
    };
    timestamp: number;
    isRead: boolean;
    persistent?: boolean;
}

interface NotificationState {
    notifications: Notification[];
    unreadCount: number;
    isOpen: boolean;
}

interface NotificationActions {
    addNotification: (notification: Omit<Notification, 'id' | 'timestamp' | 'isRead'>) => string;
    removeNotification: (id: string) => void;
    markAsRead: (id: string) => void;
    markAllAsRead: () => void;
    clearAll: () => void;
    clearByType: (type: NotificationType) => void;
    setIsOpen: (isOpen: boolean) => void;

    // Convenience methods
    success: (title: string, message?: string, options?: Partial<Notification>) => string;
    error: (title: string, message?: string, options?: Partial<Notification>) => string;
    warning: (title: string, message?: string, options?: Partial<Notification>) => string;
    info: (title: string, message?: string, options?: Partial<Notification>) => string;
}

export const useNotificationStore = create<NotificationState & NotificationActions>()(
    immer((set, get) => ({
        notifications: [],
        unreadCount: 0,
        isOpen: false,

        addNotification: (notification) => {
            const id = Math.random().toString(36).substr(2, 9);
            const newNotification: Notification = {
                ...notification,
                id,
                timestamp: Date.now(),
                isRead: false,
                duration: notification.duration ?? (notification.type === 'error' ? 0 : 5000), // Error notifications persist
            };

            set((state) => {
                state.notifications.unshift(newNotification);
                state.unreadCount += 1;

                // Auto-remove notification after duration (unless persistent or error)
                if (newNotification.duration > 0 && !newNotification.persistent) {
                    setTimeout(() => {
                        get().removeNotification(id);
                    }, newNotification.duration);
                }
            });

            return id;
        },

        removeNotification: (id) => {
            set((state) => {
                const notification = state.notifications.find(n => n.id === id);
                if (notification && !notification.isRead) {
                    state.unreadCount = Math.max(0, state.unreadCount - 1);
                }
                state.notifications = state.notifications.filter(n => n.id !== id);
            });
        },

        markAsRead: (id) => {
            set((state) => {
                const notification = state.notifications.find(n => n.id === id);
                if (notification && !notification.isRead) {
                    notification.isRead = true;
                    state.unreadCount = Math.max(0, state.unreadCount - 1);
                }
            });
        },

        markAllAsRead: () => {
            set((state) => {
                state.notifications.forEach(notification => {
                    notification.isRead = true;
                });
                state.unreadCount = 0;
            });
        },

        clearAll: () => {
            set((state) => {
                state.notifications = [];
                state.unreadCount = 0;
            });
        },

        clearByType: (type) => {
            set((state) => {
                const removedNotifications = state.notifications.filter(n => n.type === type);
                const unreadRemoved = removedNotifications.filter(n => !n.isRead).length;

                state.notifications = state.notifications.filter(n => n.type !== type);
                state.unreadCount = Math.max(0, state.unreadCount - unreadRemoved);
            });
        },

        setIsOpen: (isOpen) => {
            set((state) => {
                state.isOpen = isOpen;
            });
        },

        // Convenience methods
        success: (title, message, options) => {
            return get().addNotification({
                type: 'success',
                title,
                message,
                ...options,
            });
        },

        error: (title, message, options) => {
            return get().addNotification({
                type: 'error',
                title,
                message,
                persistent: true, // Errors are persistent by default
                ...options,
            });
        },

        warning: (title, message, options) => {
            return get().addNotification({
                type: 'warning',
                title,
                message,
                duration: 7000, // Warnings stay a bit longer
                ...options,
            });
        },

        info: (title, message, options) => {
            return get().addNotification({
                type: 'info',
                title,
                message,
                ...options,
            });
        },
    }))
);

// Hook for easier usage
export const useNotifications = () => {
    const store = useNotificationStore();
    return {
        notifications: store.notifications,
        unreadCount: store.unreadCount,
        isOpen: store.isOpen,
        addNotification: store.addNotification,
        removeNotification: store.removeNotification,
        markAsRead: store.markAsRead,
        markAllAsRead: store.markAllAsRead,
        clearAll: store.clearAll,
        clearByType: store.clearByType,
        setIsOpen: store.setIsOpen,
        success: store.success,
        error: store.error,
        warning: store.warning,
        info: store.info,
    };
};