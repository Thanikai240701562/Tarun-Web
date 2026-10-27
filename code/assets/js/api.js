/**
 * TeamForge Unified API Client
 * Manages communication between pure HTML/JS frontend and PHP backend
 */

(function(window) {
    'use strict';

    // Base API URL detection
    const getApiUrl = (endpoint) => {
        // If loaded from subdirectory, adjust relative path
        const isSubdir = window.location.pathname.includes('/events/') || 
                         window.location.pathname.includes('/teams/') || 
                         window.location.pathname.includes('/students/') || 
                         window.location.pathname.includes('/sessions/') || 
                         window.location.pathname.includes('/chat/') || 
                         window.location.pathname.includes('/ai/') || 
                         window.location.pathname.includes('/admin/');
        const base = isSubdir ? '../api/' : 'api/';
        return base + endpoint;
    };

    let csrfToken = '';

    const request = async (endpoint, method = 'GET', data = null) => {
        const url = getApiUrl(endpoint);
        const headers = {
            'X-Requested-With': 'XMLHttpRequest'
        };

        if (csrfToken) {
            headers['X-CSRF-Token'] = csrfToken;
        }

        const options = {
            method,
            headers
        };

        if (data) {
            headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(data);
        }

        try {
            const res = await fetch(url, options);
            const json = await res.json();
            if (json.csrf_token) {
                csrfToken = json.csrf_token;
            }
            return json;
        } catch (err) {
            console.warn(`[API] Network or parsing error requesting ${endpoint}:`, err);
            return { success: false, error: 'Could not connect to PHP server. Ensure Apache is running.' };
        }
    };

    window.api = {
        // Auth
        checkAuth: () => request('auth.php?action=me'),
        login: (email, password) => request('auth.php', 'POST', { action: 'login', email, password }),
        register: (formData) => request('auth.php', 'POST', { action: 'register', ...formData }),
        logout: async () => {
            const res = await request('auth.php', 'POST', { action: 'logout' });
            window.location.href = 'login.html';
            return res;
        },

        // Dashboard
        getDashboard: () => request('dashboard.php'),

        // Students
        getStudents: (params = {}) => {
            const query = new URLSearchParams(params).toString();
            return request('students.php?' + query);
        },
        inviteStudent: (data) => request('students.php', 'POST', { action: 'invite', ...data }),

        // Events
        getEvents: () => request('events.php?action=list'),
        getEvent: (id) => request(`events.php?action=view&id=${id}`),
        registerEvent: (eventId) => request('events.php', 'POST', { action: 'register', event_id: eventId }),
        unregisterEvent: (eventId) => request('events.php', 'POST', { action: 'unregister', event_id: eventId }),
        createEvent: (data) => request('events.php', 'POST', { action: 'create', ...data }),

        // Teams
        getTeams: () => request('teams.php?action=list'),
        getTeam: (id) => request(`teams.php?action=view&id=${id}`),
        createTeam: (data) => request('teams.php', 'POST', { action: 'create', ...data }),
        respondInvite: (inviteId, decision) => request('teams.php', 'POST', { action: 'respond_invite', invite_id: inviteId, decision }),

        // Classical AI
        getAiParams: () => request('ai.php?action=params'),
        runAi: (data) => request('ai.php', 'POST', { action: 'generate', ...data }),

        // Tasks (Kanban)
        getTasks: (teamId = 1) => request(`tasks.php?action=list&team_id=${teamId}`),
        createTask: (data) => request('tasks.php', 'POST', { action: 'create', ...data }),
        updateTaskStatus: (taskId, status) => request('tasks.php', 'POST', { action: 'update_status', task_id: taskId, status }),

        // Ephemeral Chat
        getChat: (teamId = 1) => request(`chat.php?action=read&team_id=${teamId}`),
        sendChat: (teamId, message) => request('chat.php', 'POST', { action: 'send', team_id: teamId, message }),

        // Sessions
        getSessions: () => request('sessions.php?action=list'),
        createSession: (data) => request('sessions.php', 'POST', { action: 'create', ...data }),

        // Profile
        getProfile: (id = null) => request('profile.php?action=get' + (id ? `&id=${id}` : '')),
        updateProfile: (data) => request('profile.php', 'POST', { action: 'update', ...data }),
        updateSkills: (skills) => request('profile.php', 'POST', { action: 'update_skills', skills }),

        // Notifications
        getNotifications: () => request('notifications.php?action=list'),
        markNotificationRead: (id = 0) => request('notifications.php', 'POST', { action: 'mark_read', id }),

        // Admin
        getAdminStats: () => request('admin.php?action=stats'),
        getAuditLogs: () => request('admin.php?action=audit_logs')
    };

})(window);
