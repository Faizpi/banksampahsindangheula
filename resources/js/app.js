import '@fontsource-variable/plus-jakarta-sans';

// Livewire 4 provides the application's Alpine.js instance.
// Do not import or start Alpine.js from this bundle.

const OFFLINE_ACTION_MESSAGE = 'Koneksi diperlukan untuk mengirim perubahan ini.';
const PUBLIC_CACHE_PREFIX = 'bank-sampah-public-';
const PUBLIC_NAV_GROUP_SELECTOR = '[data-public-nav-group]';
const PUBLIC_NAV_TRIGGER_SELECTOR = '[data-public-nav-trigger]';
const PUBLIC_NAV_MENU_SELECTOR = '[data-public-nav-menu]';
const PUBLIC_NAV_CHEVRON_SELECTOR = '[data-public-nav-chevron]';
const PUBLIC_NAV_CLOSE_DELAY = 160;
const publicNavOpenState = new WeakMap();
const publicNavCloseTimers = new WeakMap();
let publicNavPointerOrigin = null;

function publicNavGroupFromTarget(target) {
    if (!(target instanceof Element)) {
        return null;
    }

    const group = target.closest(PUBLIC_NAV_GROUP_SELECTOR);

    return group instanceof HTMLElement ? group : null;
}

function publicNavElements(group) {
    const trigger = group.querySelector(PUBLIC_NAV_TRIGGER_SELECTOR);
    const menu = group.querySelector(PUBLIC_NAV_MENU_SELECTOR);
    const chevron = group.querySelector(PUBLIC_NAV_CHEVRON_SELECTOR);

    if (!(trigger instanceof HTMLButtonElement) || !(menu instanceof HTMLElement)) {
        return null;
    }

    return { trigger, menu, chevron };
}

function clearPublicNavCloseTimer(group) {
    const timer = publicNavCloseTimers.get(group);

    if (timer === undefined) {
        return;
    }

    window.clearTimeout(timer);
    publicNavCloseTimers.delete(group);
}

function schedulePublicNavGroupClose(group) {
    clearPublicNavCloseTimer(group);

    const timer = window.setTimeout(() => {
        publicNavCloseTimers.delete(group);

        if (document.activeElement instanceof Node && group.contains(document.activeElement)) {
            return;
        }

        setPublicNavGroupOpen(group, false);
    }, PUBLIC_NAV_CLOSE_DELAY);

    publicNavCloseTimers.set(group, timer);
}

function setPublicNavGroupOpen(group, open) {
    clearPublicNavCloseTimer(group);
    const elements = publicNavElements(group);

    if (elements === null) {
        return false;
    }

    publicNavOpenState.set(group, open);
    elements.trigger.setAttribute('aria-expanded', String(open));
    elements.menu.hidden = !open;

    if (elements.chevron instanceof Element) {
        elements.chevron.classList.toggle('rotate-90', open);
    }

    return true;
}

function isPublicNavGroupOpen(group) {
    const state = publicNavOpenState.get(group);

    if (typeof state === 'boolean') {
        return state;
    }

    const elements = publicNavElements(group);

    return elements !== null
        && elements.trigger.getAttribute('aria-expanded') === 'true'
        && !elements.menu.hidden;
}

function closePublicNavGroups(except = null) {
    document.querySelectorAll(PUBLIC_NAV_GROUP_SELECTOR).forEach((candidate) => {
        if (candidate instanceof HTMLElement && candidate !== except) {
            setPublicNavGroupOpen(candidate, false);
        }
    });
}

function openPublicNavGroup(group) {
    closePublicNavGroups(group);
    setPublicNavGroupOpen(group, true);
}

function togglePublicNavGroup(group) {
    if (isPublicNavGroupOpen(group)) {
        setPublicNavGroupOpen(group, false);
        return;
    }

    openPublicNavGroup(group);
}

document.addEventListener('click', (event) => {
    const group = publicNavGroupFromTarget(event.target);
    const trigger = event.target instanceof Element
        ? event.target.closest(PUBLIC_NAV_TRIGGER_SELECTOR)
        : null;
    if (!(trigger instanceof HTMLButtonElement) || !(group instanceof HTMLElement)) {
        publicNavPointerOrigin = null;

        if (group === null) {
            closePublicNavGroups();
        }

        return;
    }

    publicNavPointerOrigin = null;
    event.stopPropagation();
    togglePublicNavGroup(group);
}, true);

document.addEventListener('pointerdown', (event) => {
    const trigger = event.target instanceof Element
        ? event.target.closest(PUBLIC_NAV_TRIGGER_SELECTOR)
        : null;

    publicNavPointerOrigin = trigger instanceof HTMLButtonElement ? trigger : null;
});

document.addEventListener('pointerup', (event) => {
    const trigger = event.target instanceof Element
        ? event.target.closest(PUBLIC_NAV_TRIGGER_SELECTOR)
        : null;

    if (!(trigger instanceof HTMLButtonElement)) {
        publicNavPointerOrigin = null;
    }
});

document.addEventListener('pointercancel', () => {
    publicNavPointerOrigin = null;
});

document.addEventListener('pointerover', (event) => {
    const group = publicNavGroupFromTarget(event.target);

    if (group === null) {
        return;
    }

    clearPublicNavCloseTimer(group);

    if (event.relatedTarget instanceof Node && group.contains(event.relatedTarget)) {
        return;
    }

    openPublicNavGroup(group);
});

document.addEventListener('pointerout', (event) => {
    const group = publicNavGroupFromTarget(event.target);

    if (group === null || event.relatedTarget instanceof Node && group.contains(event.relatedTarget)) {
        return;
    }

    if (!(document.activeElement instanceof Node) || !group.contains(document.activeElement)) {
        schedulePublicNavGroupClose(group);
    }
});

document.addEventListener('focusin', (event) => {
    const group = publicNavGroupFromTarget(event.target);

    if (group === null) {
        return;
    }

    clearPublicNavCloseTimer(group);

    const trigger = event.target instanceof Element
        ? event.target.closest(PUBLIC_NAV_TRIGGER_SELECTOR)
        : null;

    if (trigger instanceof HTMLButtonElement && trigger === publicNavPointerOrigin) {
        return;
    }

    openPublicNavGroup(group);
});

document.addEventListener('focusout', (event) => {
    const group = publicNavGroupFromTarget(event.target);

    if (group === null || event.relatedTarget instanceof Node && group.contains(event.relatedTarget)) {
        return;
    }

    setPublicNavGroupOpen(group, false);
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
        return;
    }

    let openGroup = null;

    document.querySelectorAll(PUBLIC_NAV_GROUP_SELECTOR).forEach((candidate) => {
        if (openGroup === null && candidate instanceof HTMLElement && isPublicNavGroupOpen(candidate)) {
            openGroup = candidate;
        }
    });

    if (openGroup === null) {
        return;
    }

    const elements = publicNavElements(openGroup);

    if (elements === null) {
        return;
    }

    event.preventDefault();
    elements.trigger.focus();
    closePublicNavGroups();
});

window.addEventListener('load', () => {
    const logoutConfirmed = document.body.dataset.pwaLogoutConfirmed === 'true';

    if ('serviceWorker' in navigator) {
        const registration = navigator.serviceWorker.register('/sw.js');

        if (logoutConfirmed && 'caches' in window) {
            void registration.then(
                () => clearPublicCachesAfterLogout(),
                () => clearPublicCachesAfterLogout(),
            );
        }

        return;
    }

    if (logoutConfirmed && 'caches' in window) {
        void clearPublicCachesAfterLogout();
    }
});

async function clearPublicCachesAfterLogout() {
    const cacheNames = await caches.keys();

    await Promise.all(
        cacheNames
            .filter((cacheName) => cacheName.startsWith(PUBLIC_CACHE_PREFIX))
            .map((cacheName) => caches.delete(cacheName)),
    );
}

document.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target : null;
    const photoTrigger = target?.closest('[data-photo-picker-trigger]');
    const photoRemove = target?.closest('[data-photo-picker-remove]');

    if (photoTrigger instanceof HTMLButtonElement) {
        const picker = photoPickerFromTarget(photoTrigger);
        const input = picker === null ? null : photoPickerInput(picker);

        if (input === null) {
            return;
        }

        event.preventDefault();
        if (photoTrigger.dataset.photoPickerTrigger === 'camera') {
            input.setAttribute('capture', 'environment');
        } else {
            input.removeAttribute('capture');
        }
        input.click();

        return;
    }

    if (photoRemove instanceof HTMLButtonElement) {
        const picker = photoPickerFromTarget(photoRemove);
        const input = picker === null ? null : photoPickerInput(picker);
        const index = Number.parseInt(photoRemove.dataset.photoPickerRemove ?? '', 10);

        if (input === null || !Number.isInteger(index) || index < 0) {
            return;
        }

        event.preventDefault();
        const files = Array.isArray(input._photoPickerFiles) ? [...input._photoPickerFiles] : [];
        files.splice(index, 1);
        input._photoPickerFiles = files;
        renderPhotoPickerPreview(picker, files);
        setPhotoPickerStatus(picker, files.length === 0 ? 'Belum ada foto dipilih.' : `${files.length} dari ${PICKUP_PHOTO_MAX_COUNT} foto siap dikirim.`);
        syncPhotoPickerInput(input, files);

        return;
    }

    const trigger = target?.closest('[data-public-navigation-trigger]');

    if (!(trigger instanceof HTMLElement)) {
        return;
    }

    window.dispatchEvent(new CustomEvent('open-bottom-sheet', {
        detail: {
            id: trigger.getAttribute('aria-controls'),
            invoker: trigger,
        },
    }));
});

document.addEventListener('public:offline-action-blocked', (event) => {
    if (!(event instanceof CustomEvent)) {
        return;
    }

    const liveRegions = document.querySelectorAll('[data-public-offline-status]');

    liveRegions.forEach((liveRegion) => {
        liveRegion.textContent = event.detail.message;
    });

    event.preventDefault();
});

document.addEventListener('livewire:init', () => {
    Livewire.interceptRequest(({ request }) => {
        if (navigator.onLine) {
            return;
        }

        request.cancel();
        dispatchOfflineActionBlocked('livewire');
    });
});

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || navigator.onLine) {
        return;
    }

    const isNativeStateChanging = form.method.toLowerCase() !== 'get'
        && !Array.from(form.attributes).some((attribute) => attribute.name.startsWith('wire:submit'));

    if (!isNativeStateChanging) {
        return;
    }

    blockNativeOfflineAction(event, form);
}, true);

function blockNativeOfflineAction(event, source) {
    event.preventDefault();
    event.stopImmediatePropagation();
    dispatchOfflineActionBlocked(source);
}

function dispatchOfflineActionBlocked(source) {
    const offlineEvent = new CustomEvent('public:offline-action-blocked', {
        bubbles: true,
        cancelable: true,
        detail: {
            source,
            message: OFFLINE_ACTION_MESSAGE,
        },
    });
    const wasHandled = !document.dispatchEvent(offlineEvent);

    if (!wasHandled) {
        window.alert(OFFLINE_ACTION_MESSAGE);
    }
}

const PICKUP_PHOTO_MAX_COUNT = 2;
const PICKUP_PHOTO_MAX_BYTES = 1024 * 1024;
const PICKUP_PHOTO_MAX_DIMENSION = 2000;
const PICKUP_PHOTO_QUALITIES = [0.86, 0.78, 0.7, 0.62, 0.54, 0.46];
const PICKUP_PHOTO_MIME_TYPES = ['image/jpeg', 'image/png'];

function photoPickerFromTarget(target) {
    if (!(target instanceof Element)) {
        return null;
    }

    const picker = target.closest('[data-photo-picker]');

    return picker instanceof HTMLElement ? picker : null;
}

function photoPickerInput(picker) {
    const input = picker.querySelector('[data-photo-picker-input]');

    return input instanceof HTMLInputElement ? input : null;
}

function photoPickerStatus(picker) {
    const status = picker.querySelector('[data-photo-picker-status]');

    return status instanceof HTMLElement ? status : null;
}

function photoPickerPreview(picker) {
    const preview = picker.querySelector('[data-photo-picker-preview]');

    return preview instanceof HTMLElement ? preview : null;
}

function formatPhotoSize(bytes) {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    return `${(bytes / 1024).toFixed(0)} KB`;
}

function setPhotoPickerStatus(picker, message, isError = false) {
    const status = photoPickerStatus(picker);

    if (status === null) {
        return;
    }

    status.textContent = message;
    status.classList.toggle('text-terracotta', isError);
    status.classList.toggle('text-text-secondary', !isError);
}

function renderPhotoPickerPreview(picker, files) {
    const preview = photoPickerPreview(picker);

    if (preview === null) {
        return;
    }

    preview.replaceChildren();

    files.forEach((file, index) => {
        const item = document.createElement('div');
        item.className = 'flex items-center justify-between gap-3 rounded-md border border-border bg-warm-canvas px-3 py-2 text-body-sm';

        const details = document.createElement('div');
        details.className = 'min-w-0';

        const name = document.createElement('p');
        name.className = 'truncate font-semibold text-deep-green';
        name.textContent = file.name;

        const size = document.createElement('p');
        size.className = 'text-caption text-text-secondary';
        size.textContent = formatPhotoSize(file.size);

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.dataset.photoPickerRemove = String(index);
        remove.className = 'shrink-0 rounded-md px-2 py-1 text-label font-semibold text-terracotta hover:bg-danger-bg focus:outline-none focus:ring-2 focus:ring-focus';
        remove.textContent = 'Hapus';
        remove.setAttribute('aria-label', `Hapus ${file.name}`);

        details.append(name, size);
        item.append(details, remove);
        preview.append(item);
    });
}

function syncPhotoPickerInput(input, files) {
    if (typeof DataTransfer === 'undefined') {
        return;
    }

    const dataTransfer = new DataTransfer();
    files.forEach((file) => dataTransfer.items.add(file));
    input.files = dataTransfer.files;
    input.dataset.photoPickerSyncing = 'true';
    input.dispatchEvent(new Event('change', { bubbles: true }));
}

function loadPhotoImage(file) {
    return new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const image = new Image();

        image.onload = () => {
            URL.revokeObjectURL(url);
            resolve(image);
        };
        image.onerror = () => {
            URL.revokeObjectURL(url);
            reject(new Error('Foto tidak dapat dibaca.'));
        };
        image.src = url;
    });
}

function canvasBlob(canvas, quality) {
    return new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', quality));
}

async function compressPickupPhoto(file) {
    if (!PICKUP_PHOTO_MIME_TYPES.includes(file.type)) {
        throw new Error('Gunakan foto JPEG atau PNG.');
    }

    const image = await loadPhotoImage(file);
    const scale = Math.min(1, PICKUP_PHOTO_MAX_DIMENSION / Math.max(image.naturalWidth, image.naturalHeight));
    const baseWidth = Math.max(1, Math.round(image.naturalWidth * scale));
    const baseHeight = Math.max(1, Math.round(image.naturalHeight * scale));

    for (let dimensionScale = 1; dimensionScale >= 0.25; dimensionScale *= 0.85) {
        const width = Math.max(1, Math.round(baseWidth * dimensionScale));
        const height = Math.max(1, Math.round(baseHeight * dimensionScale));
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        const context = canvas.getContext('2d');

        if (context === null) {
            throw new Error('Browser tidak mendukung kompresi foto.');
        }

        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, width, height);
        context.drawImage(image, 0, 0, width, height);

        for (const quality of PICKUP_PHOTO_QUALITIES) {
            const blob = await canvasBlob(canvas, quality);

            if (blob !== null && blob.size <= PICKUP_PHOTO_MAX_BYTES) {
                const baseName = file.name.replace(/\.[^.]+$/, '') || 'foto-sampah';

                return new File([blob], `${baseName}.jpg`, {
                    type: 'image/jpeg',
                    lastModified: Date.now(),
                });
            }
        }
    }

    throw new Error('Foto terlalu besar untuk dikompres sampai 1 MB.');
}

const photoPickerEventRoot = document.body ?? document.documentElement;

photoPickerEventRoot.addEventListener('change', (event) => {
    const input = event.target;

    if (!(input instanceof HTMLInputElement) || !input.matches('[data-photo-picker-input]')) {
        return;
    }

    if (input.dataset.photoPickerSyncing === 'true') {
        delete input.dataset.photoPickerSyncing;
        return;
    }

    const picker = photoPickerFromTarget(input);
    if (picker === null) {
        return;
    }

    const selectedFiles = Array.from(input.files ?? []);
    const existingFiles = Array.isArray(input._photoPickerFiles) ? [...input._photoPickerFiles] : [];

    if (selectedFiles.length === 0) {
        return;
    }

    event.stopImmediatePropagation();

    void (async () => {
        const maxCount = Number.parseInt(picker.dataset.photoPickerMax ?? String(PICKUP_PHOTO_MAX_COUNT), 10);
        const available = Math.max(0, maxCount - existingFiles.length);
        const filesToProcess = selectedFiles.slice(0, available);
        const compressedFiles = [];

        setPhotoPickerStatus(picker, 'Mengompres foto…');

        for (const file of filesToProcess) {
            try {
                compressedFiles.push(await compressPickupPhoto(file));
            } catch (error) {
                setPhotoPickerStatus(picker, error instanceof Error ? error.message : 'Foto tidak dapat diproses.', true);
                return;
            }
        }

        const files = [...existingFiles, ...compressedFiles].slice(0, maxCount);
        input._photoPickerFiles = files;
        renderPhotoPickerPreview(picker, files);
        setPhotoPickerStatus(picker, `${files.length} dari ${maxCount} foto siap dikirim.`);
        syncPhotoPickerInput(input, files);

        if (selectedFiles.length > filesToProcess.length) {
            setPhotoPickerStatus(picker, `Maksimal ${maxCount} foto. Hanya ${maxCount} foto pertama yang dipakai.`, true);
        }
    })();
}, true);
