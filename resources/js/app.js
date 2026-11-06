import '../css/bootstrap.scss';
import Toast from 'bootstrap/js/dist/toast';

let activeRequests = 0;
let navigationTimer;
let pageLoadingTimer;
let initialLoadingTimer;
let pageLoadingVersion = 0;
let livewireInstalled = false;
const uploads = new Set();
const recentToasts = new Map();

function setPageLoading(loading, caption = 'Menyiapkan ruang kerja Anda…') {
    const loader = document.getElementById('page-loader');
    if (loader) {
        loader.hidden = !loading;
        const label = loader.querySelector('[data-loading-caption]');
        if (label && loading) label.textContent = caption;
    }
    document.querySelectorAll('.workspace-main, .login-page').forEach((content) => {
        if (loading) content.setAttribute('aria-busy', 'true');
        else content.removeAttribute('aria-busy');
    });
    clearTimeout(navigationTimer);
    // A cancelled navigation must never leave the interface covered.
    if (loading) navigationTimer = setTimeout(() => setPageLoading(false), 12000);
}

function beginPageLoading(caption) {
    const version = ++pageLoadingVersion;
    clearTimeout(pageLoadingTimer);
    // Fast menu changes should not flash an overlay over the page.
    pageLoadingTimer = setTimeout(() => setPageLoading(true, caption), 150);

    return () => {
        if (version !== pageLoadingVersion) return;
        clearTimeout(pageLoadingTimer);
        setPageLoading(false);
    };
}

function updateProgress() {
    const progress = document.getElementById('request-progress');
    if (progress) progress.hidden = activeRequests === 0 && uploads.size === 0;
}

export function showToast(message, type = 'success', options = {}) {
    const container = document.getElementById('toast-container');
    if (!container || typeof message !== 'string' || !message.trim()) return;

    const variants = {
        success: { title: 'Berhasil', symbol: '✓' },
        error: { title: 'Terjadi kendala', symbol: '!' },
        warning: { title: 'Perhatian', symbol: '!' },
        info: { title: 'Informasi', symbol: 'i' },
    };
    if (!variants[type]) type = 'info';
    const key = `${type}:${message}`;
    if (Date.now() - (recentToasts.get(key) || 0) < 1200) return;
    recentToasts.set(key, Date.now());
    setTimeout(() => recentToasts.delete(key), 1500);

    // Keep the notification stack usable when several actions finish together.
    while (container.children.length >= 4) {
        const oldest = container.firstElementChild;
        Toast.getInstance(oldest)?.dispose();
        oldest.remove();
    }

    const toast = document.createElement('div');
    toast.className = 'toast app-toast';
    toast.dataset.type = type;
    toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
    toast.setAttribute('aria-atomic', 'true');
    toast.innerHTML = `<div class="toast-body">
        <span class="toast-symbol" aria-hidden="true"></span>
        <div class="toast-copy"><div class="toast-title"></div><div class="toast-message"></div></div>
        <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Tutup notifikasi"></button>
    </div>`;
    toast.querySelector('.toast-symbol').textContent = variants[type].symbol;
    toast.querySelector('.toast-title').textContent = options.title || variants[type].title;
    // Server messages are plain text, never executable HTML.
    toast.querySelector('.toast-message').textContent = message;
    if (options.reload) {
        const action = document.createElement('button');
        action.type = 'button';
        action.className = 'toast-action';
        action.textContent = 'Muat ulang halaman';
        action.addEventListener('click', () => window.location.reload());
        toast.querySelector('.toast-copy').append(action);
    }
    container.append(toast);
    const instance = new Toast(toast, {
        delay: type === 'success' ? 5000 : 8000,
        autohide: !options.reload,
    });
    toast.addEventListener('hidden.bs.toast', () => {
        instance.dispose();
        toast.remove();
    }, { once: true });
    instance.show();
}

function installLivewireFeedback() {
    if (livewireInstalled || !window.Livewire) return;
    livewireInstalled = true;

    Livewire.interceptRequest(({ onSend, onError, onFailure, onFinish }) => {
        let started = false;
        onSend(() => {
            started = true;
            activeRequests++;
            updateProgress();
        });
        onFinish(() => {
            if (started) activeRequests = Math.max(0, activeRequests - 1);
            updateProgress();
        });
        onError(({ response, preventDefault }) => {
            preventDefault();
            const messages = {
                401: 'Sesi login berakhir. Muat ulang halaman untuk masuk kembali.',
                403: 'Anda tidak memiliki akses untuk melakukan tindakan ini.',
                419: 'Sesi Anda telah berakhir. Muat ulang halaman dan coba kembali.',
                429: 'Terlalu banyak permintaan. Tunggu sebentar lalu coba kembali.',
            };
            showToast(messages[response.status] || 'Permintaan belum berhasil diproses. Silakan coba kembali.', 'error', {
                reload: [401, 419].includes(response.status),
            });
        });
        onFailure(() => showToast('Koneksi terputus. Periksa koneksi internet lalu coba kembali.', 'error'));
    });

    Livewire.interceptMessage(({ message, onSend, onFinish, onSuccess }) => {
        let finishPageLoading;
        onSend(({ payload }) => {
            const navigationMethods = ['navigate', 'openSettings', 'createLetter', 'createDisposition', 'createDecisionNumber'];
            const isNavigation = message.component.name === 'workspace'
                && payload.calls.some(({ method }) => navigationMethods.includes(method));
            const isLogin = message.component.name === 'login'
                && payload.calls.some(({ method }) => method === 'login');

            if (isNavigation || isLogin) {
                finishPageLoading = beginPageLoading(isLogin ? 'Memverifikasi akun Anda…' : 'Menyiapkan halaman tujuan…');
            }
        });
        onFinish(() => finishPageLoading?.());
        onSuccess(({ payload }) => {
            const snapshot = typeof payload.snapshot === 'string' ? JSON.parse(payload.snapshot) : payload.snapshot;
            const errors = Object.values(snapshot?.memo?.errors || {}).flat();
            if (errors.length) showToast(String(errors[0]), 'error', { title: 'Periksa kembali isian' });
        });
    });
}

window.addEventListener('notify', (event) => {
    const { message, type = 'success', title } = event.detail || {};
    showToast(message, type, { title });
});
document.addEventListener('livewire:init', installLivewireFeedback);
installLivewireFeedback();

document.addEventListener('click', (event) => {
    const link = event.target.closest('a[data-page-link]');
    if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    if (link.hasAttribute('download') || (link.target && link.target !== '_self')) return;
    const url = new URL(link.href, window.location.href);
    if (url.origin === location.origin && url.href !== location.href && !url.hash) setPageLoading(true);
});

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (uploads.size) {
        event.preventDefault();
        event.stopImmediatePropagation();
        showToast('Tunggu hingga unggahan dokumen selesai sebelum menyimpan.', 'warning');
        return;
    }
    if (form.hasAttribute('wire:submit') || form.hasAttribute('wire:submit.prevent') || event.defaultPrevented) return;
    if (form.classList.contains('is-submitting')) {
        event.preventDefault();
        return;
    }
    form.classList.add('is-submitting');
    setTimeout(() => {
        form.querySelectorAll('button[type="submit"]:not(:disabled)').forEach((button) => {
            button.dataset.nativeBusy = 'true';
            button.disabled = true;
        });
    }, 0);
}, true);

document.addEventListener('livewire-upload-start', (event) => {
    uploads.add(event.target);
    document.getElementById('upload-status')?.removeAttribute('hidden');
    updateProgress();
});
document.addEventListener('livewire-upload-progress', (event) => {
    const label = document.querySelector('[data-upload-label]');
    if (label) label.textContent = `Mengunggah dokumen… ${event.detail.progress}%`;
});
for (const name of ['finish', 'error', 'cancel']) {
    document.addEventListener(`livewire-upload-${name}`, (event) => {
        uploads.delete(event.target);
        if (!uploads.size) {
            document.getElementById('upload-status')?.setAttribute('hidden', '');
            const label = document.querySelector('[data-upload-label]');
            if (label) label.textContent = 'Mengunggah dokumen…';
        }
        updateProgress();
        if (name === 'error') showToast('Dokumen gagal diunggah. Periksa ukuran dan jenis file lalu coba kembali.', 'error');
    });
}

function ready() {
    clearTimeout(initialLoadingTimer);
    clearTimeout(window.esuratInitialLoaderTimeout);
    setPageLoading(false);
    const flash = document.getElementById('flash-notifications');
    if (flash) {
        JSON.parse(flash.textContent).forEach(({ message, type }) => showToast(message, type));
        flash.remove();
    }
}

if (document.readyState === 'complete') ready();
else {
    window.addEventListener('load', ready, { once: true });
    // Dismiss the initial loader if another page resource never finishes.
    initialLoadingTimer = setTimeout(() => setPageLoading(false), 8000);
}
window.addEventListener('pageshow', () => {
    clearTimeout(initialLoadingTimer);
    clearTimeout(window.esuratInitialLoaderTimeout);
    ++pageLoadingVersion;
    clearTimeout(pageLoadingTimer);
    setPageLoading(false);
    document.querySelectorAll('.is-submitting').forEach((form) => form.classList.remove('is-submitting'));
    document.querySelectorAll('[data-native-busy]').forEach((button) => {
        button.disabled = false;
        delete button.dataset.nativeBusy;
    });
});
window.addEventListener('offline', () => showToast('Anda sedang offline. Perubahan dapat dilanjutkan setelah koneksi tersedia.', 'warning'));
window.addEventListener('online', () => showToast('Koneksi internet kembali tersedia.', 'info'));
