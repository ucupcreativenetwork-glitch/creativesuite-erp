# Finance API — Fase 1–4 Akuntansi Indonesia

Base URL: `http://127.0.0.1:8000/api/v1/finance`

Semua endpoint memerlukan header:
- `Authorization: Bearer {token}`
- `Accept: application/json`

## Fase 1 — COA, Jurnal Manual, GL, Neraca Saldo

### COA
| Method | Endpoint | Permission |
|--------|----------|------------|
| GET | `/coa` | fin.coa.read |
| GET | `/coa/tree` | fin.coa.read |
| POST | `/coa` | fin.coa.create |
| PUT | `/coa/{publicId}` | fin.coa.update |

### Periode Fiskal
| GET | `/fiscal-periods?year=2026` |

### Jurnal Manual
| GET | `/journals` |
| POST | `/journals` |
| GET | `/journals/{publicId}` |
| POST | `/journals/{publicId}/post` |

**Contoh jurnal manual:**
```json
{
  "entry_date": "2026-06-17",
  "description": "Setoran modal",
  "post_immediately": true,
  "lines": [
    {"account_id": 5, "debit": 10000000, "credit": 0},
    {"account_id": 13, "debit": 0, "credit": 10000000}
  ]
}
```

### Laporan
| GET | `/reports/general-ledger?account_id=5&from_date=2026-01-01&to_date=2026-12-31` |
| GET | `/reports/trial-balance?to_date=2026-12-31` |

## Fase 2 — Auto-Jurnal Invoice & Payment

### Invoice
| POST | `/invoices` — buat draft |
| POST | `/invoices/{publicId}/post` — posting + auto-jurnal |

**Sales invoice (PPN inclusive 12%):**
```json
{
  "invoice_type": "SALES",
  "invoice_date": "2026-06-17",
  "counterparty_name": "PT Klien",
  "counterparty_npwp": "01.234.567.8-901.000",
  "is_ppn_inclusive": true,
  "ppn_rate": 12,
  "lines": [
    {"description": "Jasa instalasi CCTV", "quantity": 1, "unit_price": 11200000}
  ]
}
```

Auto-jurnal sales: Dr Piutang, Cr Pendapatan, Cr Utang PPN (jika PKP).

### Payment
| POST | `/payments` |
| POST | `/payments/{publicId}/post` |

**AR Receipt:**
```json
{
  "payment_type": "AR_RECEIPT",
  "payment_date": "2026-06-17",
  "amount": 11200000,
  "bank_account_id": 5,
  "invoice_id": 1
}
```

## Fase 3 — PPN, e-Faktur, SPT Masa PPN

| POST | `/tax/ppn/calculate` — kalkulasi DPP/PPN |
| GET | `/tax/ppn/transactions` |
| POST | `/tax/efaktur/{ppnTransactionId}/request` |
| POST | `/tax/efaktur/{publicId}/approve` |
| GET | `/tax/spt-ppn` |
| GET | `/tax/spt-ppn/{year}/{month}` |
| POST | `/tax/spt-ppn/generate` |
| POST | `/tax/spt-ppn/finalize` |

> Catatan: Perusahaan harus `is_pkp = true` agar PPN tercatat.

## Fase 4 — PPh 23 & e-Bupot

**AP Payment dengan PPh 23:**
```json
{
  "payment_type": "AP_DISBURSEMENT",
  "payment_date": "2026-06-17",
  "amount": 10000000,
  "bank_account_id": 5,
  "counterparty_name": "PT Vendor",
  "counterparty_npwp": "02.345.678.9-012.000",
  "apply_pph23": true
}
```

Auto-jurnal: Dr Hutang, Cr Bank (net), Cr Utang PPh 23.

| GET | `/tax/pph23/transactions` |
| GET | `/tax/ebupot` |
| POST | `/tax/ebupot/{pph23TransactionId}/issue` |

## Setup COA untuk tenant existing

```bash
php artisan db:seed --class=FinanceSetupSeeder
```