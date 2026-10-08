/**
 * Nama permission dari App\Enums\Permission. Dipakai untuk menyembunyikan menu dan tombol;
 * otorisasi tetap di backend.
 */
export type Permission =
    | 'customers.view'
    | 'customers.create'
    | 'customers.update'
    | 'customers.delete'
    | 'customers.activate'
    | 'customers.terminate'
    | 'customers.isolate'
    | 'packages.view'
    | 'packages.manage'
    | 'routers.manage'
    | 'invoices.view'
    | 'invoices.cancel'
    | 'invoices.resend'
    | 'payments.view'
    | 'payments.record'
    | 'payments.review'
    | 'reports.view'
    | 'settings.manage'
    | 'users.manage';
