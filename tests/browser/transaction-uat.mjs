import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import process from 'node:process';
import { chromium } from '@playwright/test';

const baseUrl = process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8001';
const database = process.env.DB_DATABASE ?? 'bank_sampah_e2e_2026080922';
const projectDir = process.cwd();
const php = process.env.PHP_BIN ?? 'C:\\Users\\Faiz\\AppData\\Local\\Microsoft\\WinGet\\Packages\\PHP.PHP.NTS.8.5_Microsoft.Winget.Source_8wekyb3d8bbwe\\php.exe';
const fixture = process.env.E2E_FIXTURE ?? `${projectDir}\\final-qc-home-360-footer.png`;
const devPassword = 'Dev#Sindangheula2026';
const customerId = Number(process.env.E2E_CUSTOMER_ID ?? '1');
const customerNumber = process.env.E2E_CUSTOMER_NUMBER ?? 'CST-DEV-1';
const dbUsername = process.env.E2E_DB_USERNAME ?? 'bank_sampah_app';
const dbPassword = process.env.E2E_DB_PASSWORD ?? process.env.DB_PASSWORD;
const appKey = process.env.E2E_APP_KEY;

if (!fs.existsSync(fixture)) {
    throw new Error(`Fixture tidak ditemukan: ${fixture}`);
}

if (!dbPassword) {
    throw new Error('Set E2E_DB_PASSWORD atau DB_PASSWORD untuk menjalankan browser UAT.');
}

const results = [];
const browserErrors = [];

function testEnv() {
    const env = {
        ...process.env,
        APP_ENV: 'local',
        DB_CONNECTION: 'mysql',
        DB_HOST: '127.0.0.1',
        DB_PORT: '3306',
        DB_DATABASE: database,
        DB_USERNAME: dbUsername,
        DB_PASSWORD: dbPassword,
        APP_URL: baseUrl,
    };

    if (appKey) {
        env.APP_KEY = appKey;
    }

    return env;
}

function tinker(expression) {
    const output = execFileSync(php, ['artisan', 'tinker', '--execute', expression], {
        cwd: projectDir,
        env: testEnv(),
        encoding: 'utf8',
        stdio: ['ignore', 'pipe', 'pipe'],
    }).trim();

    return output.split(/\r?\n/).filter(Boolean).at(-1) ?? '';
}

function tinkerJson(expression) {
    return JSON.parse(tinker(expression));
}

async function waitForLivewire(page) {
    await page.waitForTimeout(1200);
}

async function settle(page) {
    await page.waitForTimeout(450);
}

async function searchTable(page, value) {
    const search = page.locator('input[type="search"]').last();
    await search.waitFor({ state: 'visible' });
    await search.fill(value);
    await page.waitForTimeout(1200);
}

async function chooseFirst(page, name) {
    const select = page.locator(`select[name="${name}"]`);
    await select.waitFor({ state: 'visible' });
    const value = await select.locator('option').evaluateAll((options) => {
        const option = options.find((candidate) => candidate.value !== '');
        return option?.value ?? '';
    });
    if (!value) throw new Error(`Tidak ada pilihan aktif untuk ${name}`);
    await select.selectOption(value);
    await settle(page);
}

async function loginCitizen(page, role) {
    const labels = { warga: 'Warga', petugas: 'Petugas', bendahara: 'Bendahara' };
    const existingLogout = page.locator('form[action*="logout"] button').first();
    if (await existingLogout.count() && await existingLogout.isVisible().catch(() => false)) {
        await existingLogout.click().catch(() => {});
        await page.waitForTimeout(800);
    }
    await page.goto(`${baseUrl}/login`, { waitUntil: 'networkidle' });
    await page.getByRole('button', { name: new RegExp(`^${labels[role]}`) }).click();
    await page.waitForFunction((password) => document.querySelector('input[name="password"]')?.value === password, devPassword);
    await Promise.all([
        page.waitForURL(/dashboard\/(warga|petugas|bendahara)/, { timeout: 15000 }),
        page.getByRole('button', { name: 'Masuk ke Akun' }).click(),
    ]);
}

async function loginBackoffice(page, email = 'admin@sindangheula.dev') {
    await page.goto(`${baseUrl}/backoffice/login`, { waitUntil: 'networkidle' });
    await page.locator('input[type="email"]').fill(email);
    await page.locator('input[type="password"]').fill(devPassword);
    await Promise.all([
        page.waitForURL((url) => url.pathname.startsWith('/backoffice') && url.pathname !== '/backoffice/login', { timeout: 15000 }),
        page.getByRole('button', { name: /Sign in|Masuk|Login/i }).click(),
    ]);
    await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});
    await page.waitForTimeout(1000);
}

async function logout(page) {
    const button = page.locator('form[action*="logout"] button').first();
    if (await button.count()) {
        await Promise.all([
            page.waitForURL(/\/login/, { timeout: 10000 }).catch(() => {}),
            button.click(),
        ]);
    }
}

async function run(name, callback) {
    try {
        await callback();
        results.push({ name, status: 'PASS' });
        console.log(`PASS | ${name}`);
    } catch (error) {
        const message = error instanceof Error ? error.message : String(error);
        results.push({ name, status: 'FAIL', message });
        console.log(`FAIL | ${name} | ${message}`);
    }
}

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext();
const page = await context.newPage();
page.on('pageerror', (error) => browserErrors.push(`pageerror: ${error.message}`));
page.on('requestfailed', (request) => browserErrors.push(`requestfailed: ${request.method()} ${request.url()} ${request.failure()?.errorText ?? ''}`));

let pickupNumber = null;
let withdrawalNumber = null;
let withdrawalId = null;
let groceryNumber = null;
let groceryId = null;
let depositData = null;

await run('Warga mengajukan pickup dengan foto', async () => {
    await loginCitizen(page, 'warga');
    await page.goto(`${baseUrl}/warga/penjemputan/ajukan`, { waitUntil: 'networkidle' });
    await chooseFirst(page, 'serviceAreaId');
    await settle(page);
    await page.locator('input[name="selectedDate"]').fill(new Date(Date.now() + 86400000).toISOString().slice(0, 10));
    await settle(page);
    await page.locator('textarea[name="address"]').fill('Jl. UAT E2E No. 1, Sindangheula');
    await settle(page);
    await page.getByRole('button', { name: 'Lanjut ke Jenis & foto' }).click();
    await settle(page);
    await chooseFirst(page, 'items.0.waste_type_id');
    await page.locator('input[name="items.0.estimated_weight_kg"]').fill('2.500');
    await settle(page);
    await page.locator('input[name="items.0.estimated_quantity"]').fill('4');
    await settle(page);
    await page.locator('#pickup-photos').setInputFiles(fixture);
    await settle(page);
    await page.getByRole('button', { name: 'Lanjut ke Tinjau' }).click();
    await settle(page);
    await page.getByRole('button', { name: /Kirim pengajuan/i }).click();
    await page.waitForURL(/\/warga\/penjemputan\/\d+/, { timeout: 20000 });
    await waitForLivewire(page);
    const text = await page.locator('body').innerText();
    if (!text.toLowerCase().includes('menunggu pemeriksaan')) throw new Error('Detail pickup tidak menampilkan status awal.');
    pickupNumber = text.match(/PUP-[A-Z0-9-]+/)?.[0] ?? null;
    if (!pickupNumber) throw new Error('Nomor pickup tidak ditemukan.');
    await logout(page);
});

await run('Petugas memfinalisasi setoran dan saldo masuk', async () => {
    await loginCitizen(page, 'petugas');
    await page.goto(`${baseUrl}/petugas/setoran/${customerId}`, { waitUntil: 'networkidle' });
    await page.getByRole('button', { name: 'Tambah Item' }).click();
    await settle(page);
    await chooseFirst(page, 'items.0.waste_type_id');
    await chooseFirst(page, 'items.0.condition_id');
    await page.locator('input[name="items.0.weight_kg"]').fill('100.000');
    await settle(page);
    await page.locator('#deposit-evidence').setInputFiles(fixture);
    await settle(page);
    await page.getByRole('button', { name: 'Finalisasi Setoran' }).click();
    await waitForLivewire(page);
    if (!await page.getByText('Setoran berhasil difinalisasi.').isVisible()) {
        throw new Error('Pesan finalisasi setoran tidak muncul.');
    }
    depositData = tinkerJson(`$d=\\App\\Domain\\Deposits\\Models\\Deposit::where('customer_id',${customerId})->latest('id')->firstOrFail(); echo json_encode(['id'=>$d->id,'number'=>$d->deposit_number,'value'=>$d->total_value,'token'=>$d->verificationToken()]);`);
    if (depositData.value <= 0 || !depositData.token) throw new Error('Setoran final tidak menghasilkan nilai atau token QR.');
    await logout(page);
});

await run('Warga membuka bukti setoran dan QR publik', async () => {
    const qrContext = await browser.newContext();
    const qrPage = await qrContext.newPage();
    await loginCitizen(qrPage, 'warga');
    await qrPage.goto(`${baseUrl}/warga/riwayat-setoran`, { waitUntil: 'networkidle' });
    await qrPage.goto(`${baseUrl}/warga/setoran/${depositData.id}`, { waitUntil: 'networkidle' });
    const qr = qrPage.locator('img[alt^="QR verifikasi"]');
    await qr.waitFor({ state: 'visible' });
    if (!(await qr.getAttribute('src'))?.startsWith('data:image/')) throw new Error('QR receipt bukan data image.');
    await qrPage.goto(`${baseUrl}/verifikasi/setoran/${depositData.token}`, { waitUntil: 'networkidle' });
    if ((await qrPage.locator('body').innerText()).includes('404')) throw new Error('QR verifikasi publik tidak ditemukan.');
    await qrContext.close();
});

await run('Admin menerima dan menjadwalkan pickup', async () => {
    const adminContext = await browser.newContext();
    const admin = await adminContext.newPage();
    await loginBackoffice(admin);
    await admin.goto(`${baseUrl}/backoffice/pickups/models/pickup-requests`, { waitUntil: 'networkidle' });
    await searchTable(admin, pickupNumber);
    const row = admin.locator('tr').filter({ hasText: pickupNumber }).first();
    await row.waitFor({ state: 'visible' });
    await row.getByRole('button', { name: 'Terima' }).click();
    await waitForLivewire(admin);
    const acceptedRow = admin.locator('tr').filter({ hasText: pickupNumber }).first();
    await acceptedRow.getByRole('button', { name: 'Jadwalkan' }).click();
    const dialog = admin.locator('[role="dialog"]');
    await dialog.locator('select').first().selectOption({ index: 1 });
    await dialog.locator('input[type="date"]').fill(new Date(Date.now() + 86400000).toISOString().slice(0, 10));
    await dialog.getByRole('button', { name: /Kirim|Jadwalkan/ }).click();
    await waitForLivewire(admin);
    const state = tinker(`$p=\\App\\Domain\\Pickups\\Models\\PickupRequest::where('request_number','${pickupNumber}')->firstOrFail(); echo $p->status->value;`);
    if (state !== 'dijadwalkan') throw new Error(`Status pickup setelah approval: ${state}`);
    await adminContext.close();
});

await run('Petugas menyelesaikan pickup dan setoran aktual', async () => {
    await loginCitizen(page, 'petugas');
    const pickup = tinkerJson(`$p=\\App\\Domain\\Pickups\\Models\\PickupRequest::where('request_number','${pickupNumber}')->firstOrFail(); echo json_encode(['id'=>$p->id,'staff'=>$p->assigned_staff_id]);`);
    await page.goto(`${baseUrl}/petugas/penjemputan/${pickup.id}`, { waitUntil: 'networkidle' });
    await page.getByRole('button', { name: 'Menuju Lokasi' }).click();
    await waitForLivewire(page);
    await page.getByRole('button', { name: 'Tandai Dijemput' }).click();
    await waitForLivewire(page);
    await chooseFirst(page, 'actualItems.0.waste_type_id');
    await chooseFirst(page, 'actualItems.0.condition_id');
    await page.locator('input[name="actualItems.0.weight_kg"]').fill('2.250');
    await settle(page);
    await page.getByRole('button', { name: 'Finalkan Setoran Aktual' }).click();
    await waitForLivewire(page);
    const state = tinker(`$p=\\App\\Domain\\Pickups\\Models\\PickupRequest::where('request_number','${pickupNumber}')->firstOrFail(); echo $p->status->value;`);
    if (state !== 'selesai') throw new Error(`Status pickup setelah penyelesaian: ${state}`);
    await logout(page);
});

await run('Warga mengajukan pencairan dan hold saldo', async () => {
    await loginCitizen(page, 'warga');
    await page.goto(`${baseUrl}/warga/pencairan/ajukan`, { waitUntil: 'networkidle' });
    const availableText = await page.locator('text=/Saldo tersedia/').locator('..').innerText();
    const available = Number((availableText.match(/[0-9.]+/)?.[0] ?? '0').replaceAll('.', ''));
    const amount = Math.min(10000, Math.floor(available / 2500) * 2500);
    if (amount < 10000) throw new Error(`Saldo tersedia untuk pencairan hanya Rp${available}.`);
    await page.locator('input[name="amount"]').fill(String(amount));
    await settle(page);
    await page.locator('input[name="pickupDate"]').fill(new Date(Date.now() + 86400000).toISOString().slice(0, 10));
    await settle(page);
    await page.locator('textarea[name="pickupLocation"]').fill('Loket UAT Bank Sampah Sindangheula');
    await settle(page);
    await page.getByRole('button', { name: 'Ajukan Pencairan' }).click();
    await page.waitForURL(/\/warga\/pencairan\/\d+/, { timeout: 20000 });
    await waitForLivewire(page);
    const withdrawal = tinkerJson(`$w=\\App\\Domain\\Withdrawals\\Models\\WithdrawalRequest::where('customer_id',${customerId})->latest('id')->firstOrFail(); echo json_encode(['id'=>$w->id,'number'=>$w->request_number]);`);
    withdrawalId = withdrawal.id;
    withdrawalNumber = withdrawal.number;
    if (!withdrawalNumber) throw new Error('Nomor pencairan tidak ditemukan.');
    await logout(page);
});

await run('Warga mengajukan penukaran sembako', async () => {
    await loginCitizen(page, 'warga');
    await page.goto(`${baseUrl}/warga/sembako/ajukan`, { waitUntil: 'networkidle' });
    await chooseFirst(page, 'packageId');
    await page.getByRole('button', { name: 'Ajukan Paket' }).click();
    await page.waitForURL(/\/warga\/sembako\/\d+/, { timeout: 20000 });
    await waitForLivewire(page);
    const grocery = tinkerJson(`$g=\\App\\Domain\\Groceries\\Models\\GroceryRedemption::where('customer_id',${customerId})->latest('id')->firstOrFail(); echo json_encode(['id'=>$g->id,'number'=>$g->request_number]);`);
    groceryId = grocery.id;
    groceryNumber = grocery.number;
    if (!groceryNumber) throw new Error('Nomor sembako tidak ditemukan.');
    await logout(page);
});

await run('Admin menyetujui koreksi setoran melalui browser', async () => {
    const adminContext = await browser.newContext();
    const admin = await adminContext.newPage();
    await loginBackoffice(admin, 'superadmin@sindangheula.dev');
    await admin.goto(`${baseUrl}/backoffice/deposits/models/deposits`, { waitUntil: 'networkidle' });
    await searchTable(admin, depositData.number);
    const row = admin.locator('tr').filter({ hasText: depositData.number }).first();
    await row.waitFor({ state: 'visible' });
    await row.getByRole('button', { name: 'Koreksi' }).click();
    const dialog = admin.locator('.fi-modal').last();
    await dialog.locator('input[type="number"]').first().fill(String(Math.max(0, depositData.value - 1000)));
    await settle(admin);
    await dialog.locator('textarea').first().fill('Koreksi UAT: verifikasi ulang hasil timbang dan bukti.');
    await settle(admin);
    await dialog.locator('input[type="file"]').setInputFiles(fixture);
    await settle(admin);
    await dialog.getByRole('button', { name: /Konfirmasi|Kirim|Koreksi/ }).click();
    await waitForLivewire(admin);
    const state = tinker(`$d=\\App\\Domain\\Deposits\\Models\\Deposit::findOrFail(${depositData.id}); echo $d->status;`);
    if (state !== 'dikoreksi') throw new Error(`Status setoran setelah koreksi: ${state}`);
    await adminContext.close();
});

await run('Admin menyetujui sembako dan petugas mencatat handover', async () => {
    const adminContext = await browser.newContext();
    const admin = await adminContext.newPage();
    await loginBackoffice(admin);
    await admin.goto(`${baseUrl}/backoffice/groceries/models/grocery-redemptions`, { waitUntil: 'networkidle' });
    await searchTable(admin, groceryNumber);
    const row = admin.locator('tr').filter({ hasText: groceryNumber }).first();
    await row.waitFor({ state: 'visible' });
    await row.getByRole('button', { name: 'Setujui' }).click();
    const dialog = admin.locator('[role="dialog"]');
    await dialog.locator('textarea').first().fill('Stok tersedia untuk UAT dan siap diproses.');
    await settle(admin);
    await dialog.getByRole('button', { name: /Kirim|Setujui/ }).click();
    await waitForLivewire(admin);
    await adminContext.close();

    await loginCitizen(page, 'petugas');
    await page.goto(`${baseUrl}/petugas/sembako`, { waitUntil: 'networkidle' });
    const prepareButtons = page.getByRole('button', { name: 'Mulai Siapkan' });
    const prepareIndex = await prepareButtons.evaluateAll((buttons, id) => buttons.findIndex((button) => button.getAttribute('wire:click') === `prepare(${id})`), groceryId);
    if (prepareIndex < 0) throw new Error('Tombol persiapan untuk redemption UAT tidak ditemukan.');
    await prepareButtons.nth(prepareIndex).click();
    await waitForLivewire(page);
    const readyButtons = page.getByRole('button', { name: 'Tandai Siap Diambil' });
    const readyIndex = await readyButtons.evaluateAll((buttons, id) => buttons.findIndex((button) => button.getAttribute('wire:click') === `ready(${id})`), groceryId);
    if (readyIndex < 0) throw new Error('Tombol siap diambil untuk redemption UAT tidak ditemukan.');
    await readyButtons.nth(readyIndex).click();
    await waitForLivewire(page);
    const handoverButtons = page.getByRole('button', { name: 'Proses Handover' });
    const handoverIndex = await handoverButtons.evaluateAll((buttons, id) => buttons.findIndex((button) => button.getAttribute('wire:click') === `select(${id})`), groceryId);
    if (handoverIndex < 0) throw new Error('Tombol handover untuk redemption UAT tidak ditemukan.');
    await handoverButtons.nth(handoverIndex).click();
    await page.locator('select[name="recipientVerification"]').selectOption('nomor_nasabah');
    await settle(page);
    await page.locator('input[name="recipientReference"]').fill(customerNumber);
    await settle(page);
    await page.locator('#grocery-proof').setInputFiles(fixture);
    await settle(page);
    await page.getByRole('button', { name: 'Konfirmasi Handover' }).click();
    await waitForLivewire(page);
    const state = tinker(`$g=\\App\\Domain\\Groceries\\Models\\GroceryRedemption::where('request_number','${groceryNumber}')->firstOrFail(); echo $g->status->value;`);
    if (state !== 'selesai') throw new Error(`Status sembako setelah handover: ${state}`);
    await logout(page);
});

await run('Admin menyetujui dan bendahara membayar pencairan', async () => {
    const adminContext = await browser.newContext();
    const admin = await adminContext.newPage();
    await loginBackoffice(admin, 'superadmin@sindangheula.dev');
    await admin.goto(`${baseUrl}/backoffice/withdrawals/models/withdrawal-requests`, { waitUntil: 'networkidle' });
    await searchTable(admin, withdrawalNumber);
    const row = admin.locator('tr').filter({ hasText: withdrawalNumber }).first();
    await row.waitFor({ state: 'visible' });
    admin.once('dialog', (dialog) => dialog.accept());
    await row.getByRole('button', { name: 'Setujui' }).click();
    await admin.getByRole('button', { name: /Konfirmasi|Confirm/i }).last().click();
    await waitForLivewire(admin);
    const approvedRow = admin.locator('tr').filter({ hasText: withdrawalNumber }).first();
    const assignPayerButton = approvedRow.getByRole('button', { name: 'Tetapkan payer' });
    await assignPayerButton.waitFor({ state: 'visible', timeout: 10000 });
    await assignPayerButton.click();
    const dialog = admin.locator('[role="dialog"]');
    await dialog.locator('select').first().selectOption({ label: 'Bendahara Development' });
    await settle(admin);
    await dialog.getByRole('button', { name: /Kirim|Tetapkan payer/ }).click();
    await waitForLivewire(admin);
    await adminContext.close();

    await loginCitizen(page, 'bendahara');
    await page.goto(`${baseUrl}/bendahara/pencairan`, { waitUntil: 'networkidle' });
    const paymentButtons = page.getByRole('button', { name: 'Proses Pembayaran' });
    const paymentIndex = await paymentButtons.evaluateAll((buttons, id) => buttons.findIndex((button) => (button.getAttribute('wire:click') ?? '').includes(`select(${id})`)), withdrawalId);
    if (paymentIndex < 0) {
        throw new Error(`Tombol pembayaran untuk withdrawal UAT tidak ditemukan.\n${(await page.locator('body').innerText()).slice(-2500)}`);
    }
    await paymentButtons.nth(paymentIndex).click();
    await page.locator('select[name="recipientVerification"]').selectOption('nomor_nasabah');
    await settle(page);
    await page.locator('input[name="recipientReference"]').fill(customerNumber);
    await settle(page);
    await page.locator('#payment-proof').setInputFiles(fixture);
    await settle(page);
    await page.getByRole('button', { name: 'Tinjau sebelum bayar' }).click();
    await waitForLivewire(page);
    await page.getByRole('button', { name: 'Bayar dan catat bukti' }).click();
    await waitForLivewire(page);
    const state = tinker(`$w=\\App\\Domain\\Withdrawals\\Models\\WithdrawalRequest::where('request_number','${withdrawalNumber}')->firstOrFail(); echo $w->status->value;`);
    if (state !== 'sudah_dibayar') throw new Error(`Status pencairan setelah pembayaran: ${state}`);
    await logout(page);
});

await context.close();
await browser.close();

console.log(`\nBrowser errors: ${browserErrors.length}`);
for (const error of browserErrors) console.log(`  ${error}`);
console.log(JSON.stringify({ results, browserErrors, depositData, pickupNumber, withdrawalNumber, groceryNumber }, null, 2));

if (results.some((result) => result.status === 'FAIL') || browserErrors.length > 0) {
    process.exitCode = 1;
}
