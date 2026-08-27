# Mesin Kedalaman Bisnis P1 — Rencana Implementasi

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) to implement this plan task-by-task.

**Tujuan:** Meningkatkan tiga modul inti — keuangan, gaji, MRP — dari kerangka CRUD menjadi mesin komputasi bisnis tingkat industri

**Arsitektur:** Setiap mesin sebagai kelas Service independen, mengikuti konvensi direktori `app/service/{module}/`, menginjeksi dependensi melalui constructor, `DB::transaction()` menjamin konsistensi data, pengujian PHPUnit mencakup algoritma kunci

**Tumpukan Teknologi:** PHP 8.3, workerman/webman-framework 2.x, Eloquent ORM, PHPUnit 12

---

## Task 1: Mesin pembukuan berpasangan (double-entry) keuangan

**File:**
- Create: `app/service/finance/DoubleEntryService.php`
- Create: `tests/DoubleEntryServiceTest.php`
- Modify: `app/controller/finance/VoucherController.php`

### Latar Belakang

`FinanceVoucher` yang ada hanya melakukan CRUD sederhana, tanpa validasi keseimbangan debit-kredit. Tabel `FinanceVoucherItem` sudah memiliki kolom `voucher_id`, `account_subject_id`, `debit_amount`, `credit_amount`, `summary`. Aturan inti: «Setiap debit pasti ada kredit, debit dan kredit harus seimbang».

- [ ] **Step 1: Buat DoubleEntryService**

```php
<?php
// app/service/finance/DoubleEntryService.php
declare(strict_types=1);

namespace app\service\finance;

use app\common\SnowflakeService;
use app\model\FinanceVoucher;
use app\model\FinanceVoucherItem;
use Illuminate\Database\Capsule\Manager as DB;

class DoubleEntryService
{
    public function validateBalance(array $items): void
    {
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        foreach ($items as $item) {
            $totalDebit += (float)($item['debit_amount'] ?? 0);
            $totalCredit += (float)($item['credit_amount'] ?? 0);
        }
        if (abs($totalDebit - $totalCredit) > 0.001) {
            throw new \RuntimeException(sprintf(
                'Debit dan kredit tidak seimbang: total debit=%.2f, total kredit=%.2f, selisih=%.2f',
                $totalDebit, $totalCredit, abs($totalDebit - $totalCredit)
            ));
        }
    }

    public function createVoucher(array $data, array $items): FinanceVoucher
    {
        $this->validateBalance($items);

        return DB::transaction(function () use ($data, $items) {
            $voucher = new FinanceVoucher();
            $voucher->id = SnowflakeService::generate();
            $voucher->name = $data['name'] ?? '';
            $voucher->code = $data['code'] ?? ('VCH' . date('YmdHis'));
            $voucher->voucher_date = $data['voucher_date'] ?? date('Y-m-d');
            $voucher->status = 1;
            $voucher->save();

            foreach ($items as $item) {
                $vi = new FinanceVoucherItem();
                $vi->id = SnowflakeService::generate();
                $vi->voucher_id = $voucher->id;
                $vi->account_subject_id = (int)$item['account_subject_id'];
                $vi->debit_amount = (float)($item['debit_amount'] ?? 0);
                $vi->credit_amount = (float)($item['credit_amount'] ?? 0);
                $vi->summary = $item['summary'] ?? '';
                $vi->save();
            }
            return $voucher;
        });
    }

    public function audit(int $voucherId): FinanceVoucher
    {
        $voucher = FinanceVoucher::find($voucherId);
        if (!$voucher) throw new \RuntimeException('Voucher tidak ada');
        if ($voucher->status !== 1) throw new \RuntimeException('Hanya voucher berstatus tersimpan yang dapat diaudit');
        
        $items = FinanceVoucherItem::where('voucher_id', $voucherId)->get()->toArray();
        $this->validateBalance($items);
        $voucher->status = 2;
        $voucher->save();
        return $voucher;
    }

    public function reverse(int $voucherId): FinanceVoucher
    {
        $original = FinanceVoucher::find($voucherId);
        if (!$original || $original->status !== 2) {
            throw new \RuntimeException('Hanya voucher yang sudah diaudit yang dapat dihapus (write-off)');
        }
        $items = FinanceVoucherItem::where('voucher_id', $voucherId)->get()->toArray();
        $reversedItems = array_map(fn($i) => [
            'account_subject_id' => $i['account_subject_id'],
            'debit_amount' => $i['credit_amount'],
            'credit_amount' => $i['debit_amount'],
            'summary' => 'Penghapusan: ' . ($i['summary'] ?? ''),
        ], $items);
        return $this->createVoucher([
            'name' => 'Penghapusan-' . $original->name,
            'code' => 'REV-' . $original->code,
            'voucher_date' => date('Y-m-d'),
        ], $reversedItems);
    }
}
```

- [ ] **Step 2: Buat pengujian**

```php
<?php
// tests/DoubleEntryServiceTest.php
declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase;

class DoubleEntryServiceTest extends TestCase
{
    public function testBalanceValidationPasses(): void
    {
        $svc = new \app\service\finance\DoubleEntryService();
        $items = [
            ['account_subject_id' => 1, 'debit_amount' => 100, 'credit_amount' => 0, 'summary' => 'Barang inventaris'],
            ['account_subject_id' => 2, 'debit_amount' => 0, 'credit_amount' => 100, 'summary' => 'Hutang usaha'],
        ];
        $this->expectNotToPerformAssertions();
        $svc->validateBalance($items);
    }

    public function testBalanceValidationFails(): void
    {
        $svc = new \app\service\finance\DoubleEntryService();
        $items = [
            ['account_subject_id' => 1, 'debit_amount' => 100, 'credit_amount' => 0],
            ['account_subject_id' => 2, 'debit_amount' => 0, 'credit_amount' => 50],
        ];
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tidak seimbang');
        $svc->validateBalance($items);
    }

    public function testReverseSwapsDebitAndCredit(): void
    {
        $items = [['account_subject_id' => 1, 'debit_amount' => 100, 'credit_amount' => 0, 'summary' => 'Ringkasan asli']];
        $reversed = array_map(fn($i) => [
            'account_subject_id' => $i['account_subject_id'],
            'debit_amount' => $i['credit_amount'],
            'credit_amount' => $i['debit_amount'],
            'summary' => 'Penghapusan: ' . ($i['summary'] ?? ''),
        ], $items);
        $this->assertEquals(0, $reversed[0]['debit_amount']);
        $this->assertEquals(100, $reversed[0]['credit_amount']);
    }
}
```

- [ ] **Step 3: Integrasi VoucherController**

Modifikasi `store()` di `app/controller/finance/VoucherController.php`: jika permintaan berisi `items` maka panggil `DoubleEntryService::createVoucher()`, jika tidak fallback ke CRUD asli.

```php
public function store(Request $request): Response
{
    $items = $request->input('items');
    if ($items) {
        try {
            $svc = new \app\service\finance\DoubleEntryService();
            $voucher = $svc->createVoucher($request->all(), $items);
            return $this->success($this->encodeIds($voucher->toArray()), 'Voucher tersimpan');
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }
    }
    // Kompatibilitas mundur
    $validator = validator($request->all(), ['name' => 'required|string|max:200']);
    if ($validator->fails()) return $this->fail($validator->errors()->first(), 422);
    $item = new FinanceVoucher();
    $item->id = $this->generateId();
    $this->fillModelFromRequest($item, $request);
    $item->save();
    return $this->success($this->encodeIds($item->toArray()), 'Berhasil dibuat');
}
```

- [ ] **Step 4: Run tests & commit**

```bash
php vendor/bin/phpunit tests/DoubleEntryServiceTest.php --testdox
```

---

## Task 2: Mesin perhitungan gaji

**File:**
- Create: `app/service/hr/SalaryEngineService.php`
- Create: `tests/SalaryEngineServiceTest.php`

- [ ] **Step 1: Buat SalaryEngineService**

```php
<?php
// app/service/hr/SalaryEngineService.php
declare(strict_types=1);

namespace app\service\hr;

class SalaryEngineService
{
    private const TAX_BRACKETS = [
        [0, 36000, 0.03, 0],
        [36000, 144000, 0.10, 2520],
        [144000, 300000, 0.20, 16920],
        [300000, 420000, 0.25, 31920],
        [420000, 660000, 0.30, 52920],
        [660000, 960000, 0.35, 85920],
        [960000, PHP_FLOAT_MAX, 0.45, 181920],
    ];

    private const SOCIAL_INSURANCE_PERSONAL_RATE = 0.105; // pensiun8+medis2+pengangguran0.5
    private float $housingFundRate = 0.07;
    private float $siBaseMin = 3523;
    private float $siBaseMax = 26421;
    private float $hfBaseMin = 2360;
    private float $hfBaseMax = 41190;

    public function configure(array $config): void
    {
        if (isset($config['housingFundRate'])) $this->housingFundRate = (float)$config['housingFundRate'];
        if (isset($config['siBaseMin'])) $this->siBaseMin = (float)$config['siBaseMin'];
        if (isset($config['siBaseMax'])) $this->siBaseMax = (float)$config['siBaseMax'];
        if (isset($config['hfBaseMin'])) $this->hfBaseMin = (float)$config['hfBaseMin'];
        if (isset($config['hfBaseMax'])) $this->hfBaseMax = (float)$config['hfBaseMax'];
    }

    public function calculate(float $baseSalary, float $performance = 0, float $overtime = 0, float $deduction = 0): array
    {
        $gross = $baseSalary + $performance + $overtime;
        $siBase = max($this->siBaseMin, min($gross, $this->siBaseMax));
        $hfBase = max($this->hfBaseMin, min($gross, $this->hfBaseMax));
        $socialInsurance = round($siBase * self::SOCIAL_INSURANCE_PERSONAL_RATE, 2);
        $housingFund = round($hfBase * $this->housingFundRate, 2);
        $taxableIncome = $gross - $socialInsurance - $housingFund - 5000;
        $tax = $this->calculateTax(max($taxableIncome, 0));
        $net = round($gross - $socialInsurance - $housingFund - $tax - $deduction, 2);

        return [
            'gross' => round($gross, 2),
            'social_insurance' => $socialInsurance,
            'housing_fund' => $housingFund,
            'taxable_income' => round($taxableIncome, 2),
            'tax' => $tax,
            'deduction' => $deduction,
            'net' => $net,
        ];
    }

    public function calculateTax(float $annualTaxableIncome): float
    {
        $tax = 0.0;
        foreach (self::TAX_BRACKETS as [$from, $to, $rate, $quickDeduction]) {
            if ($annualTaxableIncome > $from) {
                $taxableInBracket = min($annualTaxableIncome, $to) - $from;
                $tax += $taxableInBracket * $rate;
            }
        }
        $qd = $this->getQuickDeduction($annualTaxableIncome);
        return round(max($tax - $qd, 0), 2);
    }

    private function getQuickDeduction(float $income): float
    {
        foreach (self::TAX_BRACKETS as [$from, $to, $rate, $qd]) {
            if ($income <= $to) return (float)$qd;
        }
        return 181920;
    }
}
```

- [ ] **Step 2: Buat pengujian**

```php
<?php
// tests/SalaryEngineServiceTest.php
declare(strict_types=1);

namespace tests;

use app\service\hr\SalaryEngineService;
use PHPUnit\Framework\TestCase;

class SalaryEngineServiceTest extends TestCase
{
    public function testBasicSalary(): void
    {
        $svc = new SalaryEngineService();
        $r = $svc->calculate(10000);
        $this->assertEquals(10000, $r['gross']);
        $this->assertEquals(1050, $r['social_insurance']); // 10000*0.105
        $this->assertEquals(700, $r['housing_fund']);       // 10000*0.07
        $this->assertEquals(3250, $r['taxable_income']);     // 10000-1050-700-5000
        $this->assertGreaterThanOrEqual(0, $r['tax']);
        $this->assertLessThan(10000, $r['net']);
    }

    public function testLowIncomeNoTax(): void
    {
        $svc = new SalaryEngineService();
        $r = $svc->calculate(5000);
        $this->assertEquals(0, $r['tax']);
    }

    public function testSiCap(): void
    {
        $svc = new SalaryEngineService();
        $r = $svc->calculate(50000);
        $this->assertEquals(2774.21, $r['social_insurance']); // 26421*0.105
    }

    public function testConfigureHfRate(): void
    {
        $svc = new SalaryEngineService();
        $svc->configure(['housingFundRate' => 0.12]);
        $r = $svc->calculate(10000);
        $this->assertEquals(1200, $r['housing_fund']);
    }
}
```

- [ ] **Step 3: Run tests & commit**

```bash
php vendor/bin/phpunit tests/SalaryEngineServiceTest.php --testdox
```

---

## Task 3: Mesin perencanaan kebutuhan material (MRP)

**File:**
- Create: `app/service/manufacturing/MrpEngineService.php`
- Create: `tests/MrpEngineServiceTest.php`

- [ ] **Step 1: Buat MrpEngineService**

```php
<?php
// app/service/manufacturing/MrpEngineService.php
declare(strict_types=1);

namespace app\service\manufacturing;

class MrpEngineService
{
    public function calculateNetRequirement(
        float $grossRequirement,
        float $onHandInventory,
        float $inTransitInventory = 0,
        float $allocatedQuantity = 0,
        float $safetyStock = 0
    ): float {
        $available = $onHandInventory + $inTransitInventory - $allocatedQuantity;
        $net = $grossRequirement - $available + $safetyStock;
        return max($net, 0);
    }

    /**
     * Ekspansi BOM rekursif, mengembalikan array datar item_id => quantity
     * @param array $bomTree struktur pohon [{item_id, quantity, loss_rate(%), children[]}]
     * @param float $parentQuantity jumlah kebutuhan item induk
     */
    public function explodeBom(array $bomTree, float $parentQuantity = 1): array
    {
        $requirements = [];
        foreach ($bomTree as $node) {
            $qty = $parentQuantity * (float)($node['quantity'] ?? 1);
            $lossRate = (float)($node['loss_rate'] ?? 0);
            $actualQty = $qty * (1 + $lossRate / 100);
            $itemId = (int)$node['item_id'];
            $requirements[$itemId] = ($requirements[$itemId] ?? 0) + $actualQty;
            if (!empty($node['children'])) {
                $childReqs = $this->explodeBom($node['children'], $actualQty);
                foreach ($childReqs as $childId => $childQty) {
                    $requirements[$childId] = ($requirements[$childId] ?? 0) + $childQty;
                }
            }
        }
        return $requirements;
    }

    public function generateOrderSuggestion(
        float $netRequirement,
        int $leadTimeDays,
        float $lotSize = 0,
        float $minOrderQty = 0,
        string $orderDate = ''
    ): array {
        if ($netRequirement <= 0) return ['quantity' => 0, 'suggested_date' => null];
        $qty = $lotSize > 0 ? ceil($netRequirement / $lotSize) * $lotSize : $netRequirement;
        $qty = max($qty, $minOrderQty);
        $date = $orderDate ?: date('Y-m-d');
        $suggestedDate = date('Y-m-d', strtotime("$date -{$leadTimeDays} days"));
        return ['quantity' => round($qty, 2), 'suggested_date' => $suggestedDate];
    }
}
```

- [ ] **Step 2: Buat pengujian**

```php
<?php
// tests/MrpEngineServiceTest.php
declare(strict_types=1);

namespace tests;

use app\service\manufacturing\MrpEngineService;
use PHPUnit\Framework\TestCase;

class MrpEngineServiceTest extends TestCase
{
    public function testNetRequirementBasic(): void
    {
        $svc = new MrpEngineService();
        $this->assertEquals(70, $svc->calculateNetRequirement(100, 30));
    }

    public function testNetRequirementWithSafetyStock(): void
    {
        $svc = new MrpEngineService();
        $this->assertEquals(90, $svc->calculateNetRequirement(100, 30, 0, 0, 20));
    }

    public function testNetRequirementSufficientStock(): void
    {
        $svc = new MrpEngineService();
        $this->assertEquals(0, $svc->calculateNetRequirement(50, 100, 20));
    }

    public function testBomSingleLevel(): void
    {
        $svc = new MrpEngineService();
        $bom = [
            ['item_id' => 101, 'quantity' => 2, 'loss_rate' => 5, 'children' => []],
            ['item_id' => 102, 'quantity' => 3, 'loss_rate' => 0, 'children' => []],
        ];
        $r = $svc->explodeBom($bom, 10);
        $this->assertEquals(21, $r[101]); // 10*2*1.05
        $this->assertEquals(30, $r[102]); // 10*3*1.0
    }

    public function testBomMultiLevel(): void
    {
        $svc = new MrpEngineService();
        $bom = [[
            'item_id' => 1, 'quantity' => 1, 'loss_rate' => 0,
            'children' => [
                ['item_id' => 2, 'quantity' => 3, 'loss_rate' => 10, 'children' => [
                    ['item_id' => 3, 'quantity' => 2, 'loss_rate' => 0, 'children' => []],
                ]],
            ],
        ]];
        $r = $svc->explodeBom($bom, 1);
        $this->assertEquals(1.0, $r[1]);
        $this->assertEquals(3.3, $r[2]);
        $this->assertEquals(6.6, $r[3]);
    }

    public function testOrderSuggestionLotSize(): void
    {
        $svc = new MrpEngineService();
        $r = $svc->generateOrderSuggestion(85, 7, 50);
        $this->assertEquals(100, $r['quantity']); // ceil(85/50)*50
    }

    public function testOrderSuggestionMinOrder(): void
    {
        $svc = new MrpEngineService();
        $r = $svc->generateOrderSuggestion(10, 3, 0, 100);
        $this->assertEquals(100, $r['quantity']);
    }
}
```

- [ ] **Step 3: Run tests & commit**

```bash
php vendor/bin/phpunit tests/MrpEngineServiceTest.php --testdox
```

---

## Task 4: Integrasi ke controller yang ada

**File:**
- Modify: `app/controller/finance/VoucherController.php`
- Modify: `app/controller/hr/SalaryController.php`
- Modify: `app/controller/manufacturing/MrpController.php`

- [ ] **Step 1: Integrasi VoucherController** — `store()` mendeteksi parameter `items`, jika ada gunakan `DoubleEntryService`, jika tidak gunakan CRUD asli
- [ ] **Step 2: Integrasi SalaryController** — `store()` memanggil `SalaryEngineService::calculate()` untuk menghitung detail gaji secara otomatis
- [ ] **Step 3: Integrasi MrpController** — `generate()` memanggil `MrpEngineService::explodeBom()` + `calculateNetRequirement()`
- [ ] **Step 4: Run all tests** — `php vendor/bin/phpunit --testdox`
- [ ] **Step 5: Commit**

---

## Kriteria Penerimaan

- [ ] Simpan voucher dengan debit-kredit tidak seimbang → 422 «Debit dan kredit tidak seimbang»
- [ ] `SalaryEngineService::calculate(10000)` → gross=10000, si=1050, hf=700, tax dihitung dengan benar
- [ ] `MrpEngineService::calculateNetRequirement(100, 30, 0, 0, 20)` → 90
- [ ] Ekspansi BOM multi-level rekursif hingga bahan baku, memperhitungkan tingkat kehilangan (loss rate)
- [ ] `php vendor/bin/phpunit --testdox` semua lulus
