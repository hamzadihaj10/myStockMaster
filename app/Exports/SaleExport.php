<?php

namespace App\Exports;

use App\Models\Sale;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SaleExport implements FromQuery, WithMapping, WithHeadings
{
    use Exportable;

    public function query()
    {
        return Sale::query();
    }

    /**
     * @param  Sale  $row
     * @return array
     */
    public function map($row): array
    {
        return [
            $row->product->name,
            $row->quantity,
            $row->price,
            $row->total,
            $row->created_at,
        ];
    }

    public function headings(): array
    {
        return [
            'Product',
            'Quantity',
            'Price',
            'Total',
            'Date',
        ];
    }
}
