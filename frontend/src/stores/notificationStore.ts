import { create } from 'zustand';
import { immer } from 'zustand/middleware/immer';

export interface Notification {
    id: string;
    type: 'success' | 'error' | 'warning' | 'info';
    title: string;
    description?: string;
    action?: {
        label: string;
        onClick: () => void;
    };
    duration?: number;
    persistent?: boolean;
    createdAt: Date;
}

interface NotificationState {
    notifications: Notification[];
}

interface NotificationActions {
    addNotification: (notification: Omit<Notification, 'id' | 'createdAt'>) => string;
    removeNotification: (id: string) => void;
    clearAllNotifications: () => void;
    markAsRead: (id: string) => void;
}

export const useNotificationStore = create<NotificationState & NotificationActions>()(
    immer((set, get) => ({
        notifications: [],

        addNotification: (notification) => {
            const id = crypto.randomUUID();
            const newNotification: Notification = {
                ...notification,
                id,
                createdAt: new Date(),
            };

            set((draft) => {
                draft.notifications.unshift(newNotification);
            });

            // Auto-remove after duration (default 5s) if not persistent
            if (!notification.persistent) {
                const duration = notification.duration || 5000;
                setTimeout(() => {
                    get().removeNotification(id);
                }, duration);
            }

            return id;
        },

        removeNotification: (id) => {
            set((draft) => {
                draft.notifications = draft.notifications.filter(n => n.id !== id);
            });
        },

        clearAllNotifications: () => {
            set((draft) => {
                draft.notifications = [];
            });
        },

        markAsRead: (id) => {
            // This could be used for read/unread state if needed
            console.log(`Marked notification ${id} as read`);
        },
    }))
);