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

const CUSTOMER_CARD_CANVAS_WIDTH = 1600;
const CUSTOMER_CARD_CANVAS_HEIGHT = 1009;

function customerCardFromTarget(target) {
    if (!(target instanceof Element)) {
        return null;
    }

    const page = target.closest('[data-customer-card-page]');
    const card = page?.querySelector('[data-customer-card-printable]');

    return card instanceof HTMLElement ? card : null;
}

function customerCardStatus(card, message) {
    const page = card.closest('[data-customer-card-page]');
    const status = page?.querySelector('[data-customer-card-status]');

    if (status instanceof HTMLElement) {
        status.textContent = message;
    }
}

function customerCardColor(name, fallback) {
    const color = window.getComputedStyle(document.documentElement).getPropertyValue(name).trim();

    return color === '' ? fallback : color;
}

function customerCardRoundedRectangle(context, x, y, width, height, radius, fillStyle, strokeStyle = null) {
    context.beginPath();
    context.roundRect(x, y, width, height, radius);
    context.fillStyle = fillStyle;
    context.fill();

    if (strokeStyle !== null) {
        context.strokeStyle = strokeStyle;
        context.lineWidth = 2;
        context.stroke();
    }
}

function customerCardTextLines(context, text, maxWidth) {
    const words = text.trim().split(/\s+/).filter(Boolean);
    const lines = [];
    let line = '';

    for (const word of words) {
        const candidate = line === '' ? word : `${line} ${word}`;

        if (line !== '' && context.measureText(candidate).width > maxWidth) {
            lines.push(line);
            line = word;
        } else {
            line = candidate;
        }
    }

    if (line !== '') {
        lines.push(line);
    }

    return lines.length > 0 ? lines : [''];
}

function customerCardDrawText(context, text, x, y, maxWidth, lineHeight, maxLines = 2) {
    const lines = customerCardTextLines(context, text, maxWidth).slice(0, maxLines);

    lines.forEach((line, index) => context.fillText(line, x, y + (index * lineHeight), maxWidth));

    return y + (lines.length * lineHeight);
}

function customerCardLoadImage(source) {
    return new Promise((resolve, reject) => {
        const image = new Image();

        image.onload = () => resolve(image);
        image.onerror = () => reject(new Error('Kode QR tidak dapat dipersiapkan untuk PNG.'));
        image.src = source;
    });
}

async function customerCardCanvas(card) {
    const canvas = document.createElement('canvas');
    canvas.width = CUSTOMER_CARD_CANVAS_WIDTH;
    canvas.height = CUSTOMER_CARD_CANVAS_HEIGHT;
    const context = canvas.getContext('2d');

    if (context === null) {
        throw new Error('Browser tidak mendukung pembuatan kartu PNG.');
    }

    const forest = customerCardColor('--color-forest-600', '#1e6a56');
    const forestDark = customerCardColor('--color-forest-700', '#185746');
    const deepGreen = customerCardColor('--color-deep-green', '#123d32');
    const surface = customerCardColor('--color-surface', '#ffffff');
    const warmCanvas = customerCardColor('--color-warm-canvas', '#f6f5ef');
    const textSecondary = customerCardColor('--color-text-secondary', '#55635d');
    const border = customerCardColor('--color-border', '#d9e1dc');
    const name = card.querySelector('[data-customer-card-name]')?.textContent?.trim() ?? 'Nasabah Bank Sampah';
    const number = card.querySelector('[data-customer-card-number]')?.textContent?.trim() ?? '';
    const area = card.querySelector('[data-customer-card-area]')?.textContent?.trim() ?? 'Desa Sindangheula';
    const qrSource = card.querySelector('[data-customer-card-qr]')?.getAttribute('src') ?? '';

    context.save();
    context.beginPath();
    context.roundRect(0, 0, canvas.width, canvas.height, 34);
    context.clip();

    context.fillStyle = surface;
    context.fillRect(0, 0, canvas.width, canvas.height);

    context.save();
    context.globalAlpha = 0.08;
    context.strokeStyle = forest;
    context.lineWidth = 1;
    for (let x = 0; x <= canvas.width; x += 48) {
        context.beginPath();
        context.moveTo(x, 0);
        context.lineTo(x, canvas.height);
        context.stroke();
    }
    for (let y = 0; y <= canvas.height; y += 48) {
        context.beginPath();
        context.moveTo(0, y);
        context.lineTo(canvas.width, y);
        context.stroke();
    }
    context.restore();

    context.fillStyle = forest;
    context.fillRect(0, 0, canvas.width, 164);
    context.strokeStyle = forestDark;
    context.lineWidth = 2;
    context.beginPath();
    context.moveTo(0, 164);
    context.lineTo(canvas.width, 164);
    context.stroke();

    context.fillStyle = surface;
    context.font = "700 24px 'Plus Jakarta Sans', sans-serif";
    context.fillText('KARTU NASABAH DIGITAL', 72, 82);
    context.fillStyle = surface;
    context.font = "700 34px 'Plus Jakarta Sans', sans-serif";
    context.fillText('Bank Sampah Sindangheula', 72, 130);

    customerCardRoundedRectangle(context, 1224, 60, 242, 56, 28, surface, border);
    context.fillStyle = forest;
    context.beginPath();
    context.arc(1259, 88, 8, 0, Math.PI * 2);
    context.fill();
    context.font = "700 22px 'Plus Jakarta Sans', sans-serif";
    context.fillText('AKTIF', 1281, 96);

    context.fillStyle = textSecondary;
    context.font = "600 22px 'Plus Jakarta Sans', sans-serif";
    context.fillText('NAMA NASABAH', 72, 292);
    context.fillStyle = deepGreen;
    context.font = "700 62px 'Plus Jakarta Sans', sans-serif";
    const nextNameY = customerCardDrawText(context, name, 72, 366, 800, 74);

    const detailsY = Math.max(550, nextNameY + 78);
    context.strokeStyle = border;
    context.lineWidth = 2;
    context.beginPath();
    context.moveTo(72, detailsY - 42);
    context.lineTo(872, detailsY - 42);
    context.stroke();

    context.fillStyle = textSecondary;
    context.font = "600 22px 'Plus Jakarta Sans', sans-serif";
    context.fillText('NOMOR NASABAH', 72, detailsY);
    context.fillText('WILAYAH LAYANAN', 72, detailsY + 128);
    context.fillStyle = deepGreen;
    context.font = "700 34px 'Plus Jakarta Sans', sans-serif";
    context.fillText(number, 72, detailsY + 48);
    customerCardDrawText(context, area, 72, detailsY + 176, 800, 40, 2);

    customerCardRoundedRectangle(context, 1044, 222, 416, 416, 24, surface, border);
    if (qrSource !== '') {
        const qr = await customerCardLoadImage(qrSource);
        context.drawImage(qr, 1068, 246, 368, 368);
    } else {
        context.fillStyle = warmCanvas;
        context.fillRect(1068, 246, 368, 368);
        context.fillStyle = textSecondary;
        context.font = "600 26px 'Plus Jakarta Sans', sans-serif";
        context.textAlign = 'center';
        context.fillText('QR belum aktif', 1252, 442);
        context.textAlign = 'left';
    }

    context.fillStyle = textSecondary;
    context.font = "600 20px 'Plus Jakarta Sans', sans-serif";
    context.textAlign = 'center';
    context.fillText('PINDAI UNTUK VERIFIKASI', 1252, 688);
    context.textAlign = 'left';

    context.fillStyle = warmCanvas;
    context.fillRect(0, 868, canvas.width, 141);
    context.fillStyle = textSecondary;
    context.font = "600 21px 'Plus Jakarta Sans', sans-serif";
    context.fillText('QR tanpa data saldo · Gunakan di layanan resmi Bank Sampah Sindangheula', 72, 948);

    context.restore();

    return canvas;
}

async function renderCustomerCardPreview(card) {
    const preview = card.querySelector('[data-customer-card-preview-image]');
    const fallback = card.querySelector('[data-customer-card-preview-fallback]');

    if (!(preview instanceof HTMLImageElement) || !(fallback instanceof HTMLElement)) {
        return;
    }

    try {
        const canvas = await customerCardCanvas(card);
        preview.src = canvas.toDataURL('image/png');
        preview.hidden = false;
        fallback.hidden = true;
        card.dataset.customerCardPreviewState = 'ready';
    } catch (error) {
        card.dataset.customerCardPreviewState = 'fallback';
    }
}

function renderCustomerCardPreviews() {
    document.querySelectorAll('[data-customer-card-printable]').forEach((card) => {
        if (card instanceof HTMLElement && card.dataset.customerCardPreviewState !== 'ready') {
            void renderCustomerCardPreview(card);
        }
    });
}

function scheduleCustomerCardPreviews() {
    if (document.fonts?.ready) {
        void document.fonts.ready.then(renderCustomerCardPreviews);
        return;
    }

    renderCustomerCardPreviews();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', scheduleCustomerCardPreviews, { once: true });
} else {
    scheduleCustomerCardPreviews();
}

window.addEventListener('livewire:navigated', scheduleCustomerCardPreviews);
document.addEventListener('livewire:navigated', scheduleCustomerCardPreviews);
window.addEventListener('load', () => window.setTimeout(scheduleCustomerCardPreviews, 250));

function customerCardDownloadFilename(card) {
    const number = card.querySelector('[data-customer-card-number]')?.textContent?.trim() ?? 'nasabah';
    const safeNumber = number.replace(/[^A-Za-z0-9_-]+/g, '-').replace(/^-+|-+$/g, '') || 'nasabah';

    return `kartu-nasabah-${safeNumber}.png`;
}

async function downloadCustomerCardPng(button, card) {
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    customerCardStatus(card, 'Menyiapkan kartu PNG.');

    try {
        const canvas = await customerCardCanvas(card);
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));

        if (blob === null) {
            throw new Error('Kartu PNG tidak dapat dibuat.');
        }

        const url = URL.createObjectURL(blob);
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = customerCardDownloadFilename(card);
        document.body.append(anchor);
        anchor.click();
        anchor.remove();
        window.setTimeout(() => URL.revokeObjectURL(url), 1000);
        customerCardStatus(card, 'Kartu PNG berhasil diunduh dan siap dicetak.');
    } catch (error) {
        customerCardStatus(card, error instanceof Error ? error.message : 'Kartu PNG tidak dapat dibuat. Coba lagi.');
    } finally {
        button.disabled = false;
        button.removeAttribute('aria-busy');
    }
}

document.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target : null;
    const cardDownload = target?.closest('[data-customer-card-download]');

    if (cardDownload instanceof HTMLButtonElement) {
        const card = customerCardFromTarget(cardDownload);

        if (card !== null) {
            event.preventDefault();
            void downloadCustomerCardPng(cardDownload, card);
        }

        return;
    }

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
        const removeMethod = picker.dataset.photoPickerRemoveMethod;
        const wire = photoPickerWire(picker);
        if (typeof removeMethod !== 'string' || removeMethod === '' || wire === null) {
            setPhotoPickerStatus(picker, 'Foto tidak dapat dihapus. Muat ulang halaman lalu coba lagi.', true);

            return;
        }

        const files = photoPickerFiles(input, picker);
        const previousFiles = [...files];
        files.splice(index, 1);
        input._photoPickerFiles = files;
        renderPhotoPickerPreview(picker, files);
        setPhotoPickerStatus(picker, photoPickerStatusMessage(files.length, photoPickerMaxCount(picker)));
        setPhotoPickerBusy(picker, true);
        void wire.$call(removeMethod, index)
            .catch(() => {
                input._photoPickerFiles = previousFiles;
                renderPhotoPickerPreview(picker, previousFiles);
                setPhotoPickerStatus(picker, 'Foto tidak dapat dihapus. Coba lagi.', true);
            })
            .finally(() => setPhotoPickerBusy(picker, false));

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

function photoPickerMaxCount(picker) {
    const configured = Number.parseInt(picker.dataset.photoPickerMax ?? '', 10);

    return Number.isInteger(configured) && configured > 0 ? configured : PICKUP_PHOTO_MAX_COUNT;
}

function photoPickerStatusMessage(count, maxCount) {
    return count === 0
        ? 'Belum ada foto dipilih.'
        : `${count} dari ${maxCount} foto siap dikirim.`;
}

function photoPickerFiles(input, picker) {
    if (Array.isArray(input._photoPickerFiles)) {
        return input._photoPickerFiles;
    }

    let initialFiles = [];
    try {
        const encoded = picker.dataset.photoPickerInitialFiles;
        initialFiles = typeof encoded === 'string' && encoded !== '' ? JSON.parse(encoded) : [];
    } catch {
        initialFiles = [];
    }

    input._photoPickerFiles = Array.isArray(initialFiles)
        ? initialFiles
            .filter((file) => file !== null && typeof file === 'object')
            .map((file) => ({
                name: typeof file.name === 'string' ? file.name : 'Foto sampah',
                size: Number.isFinite(Number(file.size)) ? Number(file.size) : 0,
                previewUrl: typeof file.previewUrl === 'string' ? file.previewUrl : '',
            }))
        : [];

    return input._photoPickerFiles;
}

function photoPickerWire(picker) {
    const componentRoot = picker.closest('[wire\\:id]');
    const componentId = componentRoot?.getAttribute('wire:id');

    if (typeof componentId !== 'string' || componentId === '' || typeof window.Livewire?.find !== 'function') {
        return null;
    }

    return window.Livewire.find(componentId);
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
        item.className = 'flex min-w-0 items-center justify-between gap-3 rounded-md border border-border bg-warm-canvas p-2 text-body-sm';

        const summary = document.createElement('div');
        summary.className = 'flex min-w-0 items-center gap-3';

        const imageSource = file instanceof File ? URL.createObjectURL(file) : file.previewUrl;
        if (typeof imageSource === 'string' && imageSource !== '') {
            const image = document.createElement('img');
            image.className = 'size-12 shrink-0 rounded-sm border border-border bg-surface object-cover';
            image.src = imageSource;
            image.alt = `Pratinjau ${file.name}`;
            if (file instanceof File) {
                image.addEventListener('load', () => URL.revokeObjectURL(imageSource), { once: true });
            }
            summary.append(image);
        }

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
        summary.append(details);
        item.append(summary, remove);
        preview.append(item);
    });
}

function setPhotoPickerBusy(picker, isBusy) {
    picker.toggleAttribute('aria-busy', isBusy);
    picker.dataset.photoPickerBusy = isBusy ? 'true' : 'false';

    picker.querySelectorAll('[data-photo-picker-trigger], [data-photo-picker-input]').forEach((element) => {
        if (element instanceof HTMLButtonElement || element instanceof HTMLInputElement) {
            element.disabled = isBusy;
        }
    });
}

function uploadPhotoPickerFile(picker, property, file) {
    const wire = photoPickerWire(picker);
    if (wire === null || typeof wire.$upload !== 'function') {
        return Promise.reject(new Error('Uploader belum siap. Muat ulang halaman lalu coba lagi.'));
    }

    return new Promise((resolve, reject) => {
        wire.$upload(
            property,
            file,
            () => resolve(),
            () => reject(new Error('Foto gagal diunggah. Periksa koneksi lalu coba lagi.')),
        );
    });
}

function hydratePhotoPicker(picker) {
    const input = photoPickerInput(picker);
    if (input === null) {
        return;
    }

    const files = photoPickerFiles(input, picker);
    renderPhotoPickerPreview(picker, files);
    setPhotoPickerStatus(picker, photoPickerStatusMessage(files.length, photoPickerMaxCount(picker)));
}

function hydratePhotoPickers(root = document) {
    if (root instanceof HTMLElement && root.matches('[data-photo-picker]')) {
        hydratePhotoPicker(root);
    }

    if (root instanceof Document || root instanceof HTMLElement) {
        root.querySelectorAll('[data-photo-picker]').forEach((picker) => {
            if (picker instanceof HTMLElement) {
                hydratePhotoPicker(picker);
            }
        });
    }
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

    const picker = photoPickerFromTarget(input);
    const property = input.dataset.photoPickerProperty;
    if (picker === null || typeof property !== 'string' || property === '') {
        return;
    }

    if (picker.dataset.photoPickerBusy === 'true') {
        input.value = '';

        return;
    }

    const selectedFiles = Array.from(input.files ?? []);
    const existingFiles = photoPickerFiles(input, picker);

    if (selectedFiles.length === 0) {
        return;
    }

    event.stopImmediatePropagation();

    void (async () => {
        const maxCount = photoPickerMaxCount(picker);
        const available = Math.max(0, maxCount - existingFiles.length);
        const filesToProcess = selectedFiles.slice(0, available);
        const compressedFiles = [];

        if (available === 0) {
            setPhotoPickerStatus(picker, `Maksimal ${maxCount} foto sudah dipilih. Hapus salah satu foto sebelum menambahkan lagi.`, true);
            input.value = '';

            return;
        }

        setPhotoPickerBusy(picker, true);
        setPhotoPickerStatus(picker, 'Mengompres foto…');

        try {
            for (const file of filesToProcess) {
                compressedFiles.push(await compressPickupPhoto(file));
            }

            for (const file of compressedFiles) {
                setPhotoPickerStatus(picker, `Mengunggah foto ${existingFiles.length + 1} dari ${maxCount}…`);
                await uploadPhotoPickerFile(picker, property, file);
                existingFiles.push(file);
                input._photoPickerFiles = existingFiles;
                renderPhotoPickerPreview(picker, existingFiles);
                setPhotoPickerStatus(picker, photoPickerStatusMessage(existingFiles.length, maxCount));
            }

            if (selectedFiles.length > filesToProcess.length) {
                setPhotoPickerStatus(picker, `Maksimal ${maxCount} foto. Hanya ${filesToProcess.length} foto pertama yang ditambahkan.`, true);
            }
        } catch (error) {
            setPhotoPickerStatus(picker, error instanceof Error ? error.message : 'Foto tidak dapat diproses.', true);
        } finally {
            input.value = '';
            setPhotoPickerBusy(picker, false);
        }
    })();
}, true);

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => hydratePhotoPickers(), { once: true });
} else {
    hydratePhotoPickers();
}

const photoPickerObserver = new MutationObserver((records) => {
    records.forEach((record) => {
        record.addedNodes.forEach((node) => {
            if (node instanceof HTMLElement) {
                hydratePhotoPickers(node);
            }
        });
    });
});

photoPickerObserver.observe(document.body ?? document.documentElement, { childList: true, subtree: true });
