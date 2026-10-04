(function () {
    'use strict';

    var app = angular.module('campusFindApp', []);
    app.controller('PageController', function ($scope, $http, $q, $window) {
        var page = document.body.getAttribute('data-page') || 'home';
        var base = document.body.getAttribute('data-base') || '';
        var csrfToken = '';
        var api = base + 'api/';
        var itemRequestId = 0;

        $scope.page = page;
        $scope.assetBase = base;
        $scope.user = null;
        $scope.message = '';
        $scope.messageType = 'success';
        $scope.busy = false;
        $scope.items = [];
        $scope.availableItems = [];
        $scope.matches = [];
        $scope.categories = [];
        $scope.locations = [];
        $scope.filters = { q: '', type: '', category_id: '', location_id: '', date: '', status: '' };
        $scope.form = {};
        $scope.stats = {};
        $scope.reports = [];
        $scope.notifications = [];
        $scope.claims = [];
        $scope.adminRows = [];
        $scope.unreadCount = 0;

        function notify(message, type) {
            $scope.message = message || '';
            $scope.messageType = type || 'success';
        }

        function request(method, path, data, upload) {
            var config = {
                method: method,
                url: api + path,
                withCredentials: true,
                headers: {}
            };
            if (method !== 'GET' && csrfToken) {
                config.headers['X-CSRF-Token'] = csrfToken;
            }
            if (upload) {
                config.data = data;
                config.transformRequest = angular.identity;
                config.headers['Content-Type'] = undefined;
            } else if (data !== undefined) {
                config.data = data;
            }
            return $http(config).then(function (response) {
                var result = response.data || {};
                if (!result.success) {
                    return $q.reject({ status: response.status, data: result });
                }
                return result.data || {};
            }).catch(function (error) {
                if (error && error.status === 401 && !['home', 'browse', 'login', 'register', 'detail'].includes(page)) {
                    $window.location.href = base + 'login.html';
                }
                if (error && error.status === 403 && page.indexOf('admin-') === 0) {
                    $window.location.href = base + 'dashboard.html';
                }
                return $q.reject(error);
            });
        }

        function handleError(error) {
            var response = error && error.data ? error.data : {};
            notify(response.message || 'Unable to complete the request. Please try again.', 'error');
        }

        function loadOptions() {
            return request('GET', 'items.php?action=options').then(function (data) {
                $scope.categories = data.categories || [];
                $scope.locations = data.locations || [];
            }).catch(handleError);
        }

        $scope.imageUrl = function (image) {
            return image ? base + image : base + 'assets/images/default-item.svg';
        };

        $scope.toggleNav = function () {
            $scope.navOpen = !$scope.navOpen;
        };

        $scope.submitAuth = function () {
            $scope.busy = true;
            var action = page === 'register' ? 'register' : 'login';
            if (page === 'register' && $scope.form.password !== $scope.form.confirm_password) {
                notify('Passwords do not match.', 'error');
                $scope.busy = false;
                return;
            }
            request('POST', 'auth.php?action=' + action, $scope.form).then(function (data) {
                if (data.csrf_token) {
                    csrfToken = data.csrf_token;
                }
                if (page === 'register') {
                    notify('Registration complete. Please log in.');
                    $window.setTimeout(function () { $window.location.href = base + 'login.html'; }, 900);
                } else {
                    $window.location.href = base + ((data.user && data.user.role === 'admin') ? 'admin/index.html' : 'dashboard.html');
                }
            }).catch(handleError).finally(function () { $scope.busy = false; });
        };

        $scope.logout = function () {
            request('POST', 'auth.php?action=logout', {}).then(function () {
                $window.location.href = base + 'index.html';
            }).catch(handleError);
        };

        $scope.submitReport = function (formElement) {
            var payload = new FormData(formElement);
            $scope.busy = true;
            request('POST', 'items.php?action=create', payload, true).then(function (data) {
                notify('Report submitted successfully.');
                $window.location.href = base + 'item-details.html?id=' + encodeURIComponent(data.item_id);
            }).catch(handleError).finally(function () { $scope.busy = false; });
        };

        $scope.submitClaim = function () {
            var payload = angular.copy($scope.form);
            payload.item_id = new URLSearchParams($window.location.search).get('item_id') || payload.item_id;
            $scope.busy = true;
            request('POST', 'claims.php?action=create', payload).then(function () {
                notify('Claim submitted. You can follow its progress below.');
                $scope.form = {};
                loadClaims();
            }).catch(handleError).finally(function () { $scope.busy = false; });
        };

        function loadClaims() {
            request('GET', 'claims.php?action=list').then(function (data) {
                $scope.claims = data.claims || [];
            }).catch(handleError);
        }

        $scope.markRead = function (id) {
            request('POST', 'notifications.php?action=read', id ? { id: id } : {}).then(function () {
                $scope.notifications.forEach(function (note) {
                    if (!id || Number(note.id) === Number(id)) {
                        note.is_read = 1;
                    }
                });
                $scope.unreadCount = $scope.notifications.filter(function (note) { return !Number(note.is_read); }).length;
                notify('Notifications marked as read.');
            }).catch(handleError);
        };
        $scope.isRead = function (note) {
            return Number(note.is_read) === 1;
        };

        $scope.adminAction = function (action, row, value) {
            var data = row ? { id: row.id, status: value } : {};
            if (action === 'category-create' || action === 'location-create') {
                data = angular.copy($scope.form);
            }
            if (action === 'category-update' || action === 'location-update') {
                data = { id: row.id, name: row.name, description: row.description };
            }
            if (action === 'category-delete' || action === 'location-delete' || action === 'item-delete') {
                if (!$window.confirm('Delete this record? This action cannot be undone.')) {
                    return;
                }
            }
            request('POST', 'admin.php?action=' + action, data).then(function (result) {
                notify(result.message || 'Saved.');
                $scope.form = {};
                loadAdmin();
            }).catch(handleError);
        };

        function loadAdmin() {
            var action = page.replace('admin-', '');
            if (action === 'index') {
                action = 'dashboard';
            }
            request('GET', 'admin.php?action=' + action).then(function (data) {
                if (action === 'dashboard') {
                    $scope.stats = data;
                    return;
                }
                $scope.adminRows = data[action] || [];
            }).catch(handleError);
        }

        function loadPage() {
            if (page === 'home') {
                loadOptions();
                request('GET', 'items.php?action=home').then(function (data) {
                    $scope.stats = data.stats || {};
                    $scope.items = data.items || [];
                }).catch(handleError);
            } else if (page === 'browse') {
                loadOptions();
                request('GET', 'items.php?action=list').then(function (data) { $scope.items = data.items || []; }).catch(handleError);
            } else if (page === 'detail') {
                var id = new URLSearchParams($window.location.search).get('id');
                request('GET', 'items.php?action=detail&id=' + encodeURIComponent(id || '')).then(function (data) {
                    $scope.item = data.item;
                    $scope.matches = data.matches || [];
                }).catch(handleError);
            } else if (page === 'report-lost' || page === 'report-found') {
                loadOptions();
                $scope.form.type = page === 'report-lost' ? 'lost' : 'found';
            } else if (page === 'dashboard') {
                request('GET', 'auth.php?action=session').then(function (data) {
                    $scope.user = data.user;
                    request('GET', 'items.php?action=dashboard').then(function (dash) {
                        $scope.stats = dash.stats || {};
                        $scope.reports = dash.reports || [];
                        $scope.matches = dash.matches || [];
                        $scope.notifications = dash.notifications || [];
                    }).catch(handleError);
                }).catch(handleError);
            } else if (page === 'profile') {
                request('GET', 'auth.php?action=session').then(function (data) {
                    $scope.user = data.user;
                    $scope.form = angular.copy(data.user || {});
                }).catch(handleError);
            } else if (page === 'claims') {
                loadClaims();
                var itemId = new URLSearchParams($window.location.search).get('item_id');
                if (itemId) {
                    request('GET', 'items.php?action=detail&id=' + encodeURIComponent(itemId)).then(function (data) { $scope.item = data.item; }).catch(handleError);
                } else {
                    request('GET', 'claims.php?action=available').then(function (data) { $scope.availableItems = data.items || []; }).catch(handleError);
                }
            } else if (page === 'notifications') {
                request('GET', 'notifications.php?action=list').then(function (data) {
                    $scope.notifications = data.notifications || [];
                    $scope.unreadCount = data.unread_count || 0;
                }).catch(handleError);
            } else if (page === 'admin-index' || page.indexOf('admin-') === 0) {
                loadAdmin();
            }
        }

        $scope.saveProfile = function () {
            request('POST', 'auth.php?action=profile', $scope.form).then(function (data) {
                $scope.user = data.user;
                notify('Profile updated.');
            }).catch(handleError);
        };

        $scope.loadItems = function () {
            var currentRequest = ++itemRequestId;
            var params = new URLSearchParams();
            Object.keys($scope.filters).forEach(function (key) {
                var value = $scope.filters[key];
                if (value instanceof Date) {
                    value = value.getFullYear() + '-' + String(value.getMonth() + 1).padStart(2, '0') + '-' + String(value.getDate()).padStart(2, '0');
                }
                if (value) { params.set(key, value); }
            });
            request('GET', 'items.php?action=list&' + params.toString()).then(function (data) {
                if (currentRequest === itemRequestId) {
                    $scope.items = data.items || [];
                }
            }).catch(function (error) {
                if (currentRequest === itemRequestId) { handleError(error); }
            });
        };

        request('GET', 'auth.php?action=session').then(function (data) {
            $scope.user = data.user;
            csrfToken = data.csrf_token || '';
            loadPage();
        }).catch(handleError);
    });

    document.addEventListener('DOMContentLoaded', function () {
        var flash = document.querySelector('.flash-message');
        if (flash) {
            setTimeout(function () {
                flash.style.opacity = '0';
                setTimeout(function () { if (flash.parentNode) { flash.parentNode.removeChild(flash); } }, 300);
            }, 3200);
        }
    });
}());
