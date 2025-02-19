<?php

declare(strict_types=1);

namespace App\Http\Livewire\SaleReturn;

use App\Enums\PaymentStatus;
use App\Http\Livewire\WithSorting;
use App\Imports\SaleImport;
use App\Models\Customer;
use App\Models\SaleReturn;
use App\Models\SaleReturnPayment;
use App\Traits\Datatable;
use Illuminate\Support\Facades\Gate;
use Jantinnerezo\LivewireAlert\LivewireAlert;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Throwable;

class Index extends Component
{
    use WithPagination;
    use WithSorting;
    use WithFileUploads;
    use LivewireAlert;
    use Datatable;

    public $salereturn;

    /** @var array<string> */
    public $listeners = [
        'showModal',
        'importModal', 'import',
        'refreshIndex' => '$refresh',
        'paymentModal', 'paymentSave',
    ];

    public $showModal = false;

    public $importModal = false;

    public $paymentModal = false;

    public $salereturn_id;
    public $date;
    public $reference;
    public $amount;
    public $payment_method;

    public $listsForFields = [];

    /** @var array<array<string>> */
    protected $queryString = [
        'search' => [
            'except' => '',
        ],
        'sortBy' => [
            'except' => 'id',
        ],
        'sortDirection' => [
            'except' => 'desc',
        ],
    ];

    /** @var array */
    protected $rules = [
        'customer_id' => 'required|numeric',
        'reference' => 'required|string|max:255',
        'tax_percentage' => 'required|integer|min:0|max:100',
        'discount_percentage' => 'required|integer|min:0|max:100',
        'shipping_amount' => 'required|numeric',
        'total_amount' => 'required|numeric',
        'paid_amount' => 'required|numeric',
        'status' => 'required|integer|min:0|max:100',
        'payment_method' => 'required|integer|min:0|max:100',
        'note' => 'string|nullable|max:1000',
    ];

    public function mount(): void
    {
        $this->sortBy = 'id';
        $this->sortDirection = 'desc';
        $this->perPage = 100;
        $this->paginationOptions = config('project.pagination.options');
        $this->orderable = (new SaleReturn())->orderable;
        $this->initListsForFields();
    }

    public function render()
    {
        abort_if(Gate::denies('sale_access'), 403);

        $query = SaleReturn::with(['customer', 'saleReturnPayments', 'saleReturnDetails'])
            ->advancedFilter([
                's' => $this->search ?: null,
                'order_column' => $this->sortBy,
                'order_direction' => $this->sortDirection,
            ]);

        $salereturns = $query->paginate($this->perPage);

        return view('livewire.sale-return.index', compact('salereturns'));
    }

    public function showModal(SaleReturn $salereturn)
    {
        abort_if(Gate::denies('sale_access'), 403);

        $this->salereturn = SaleReturn::find($salereturn->id);

        $this->showModal = true;
    }

    public function deleteSelected()
    {
        abort_if(Gate::denies('delete_sales'), 403);

        SaleReturn::whereIn('id', $this->selected)->delete();

        $this->resetSelected();
    }

    public function delete(SaleReturn $product)
    {
        abort_if(Gate::denies('delete_sales'), 403);

        $product->delete();

        $this->emit('refreshIndex');

        $this->alert('success', 'SaleReturn deleted successfully.');
    }

    public function importModal()
    {
        abort_if(Gate::denies('sale_create'), 403);

        $this->resetSelected();

        $this->resetValidation();

        $this->importModal = true;
    }

    public function import()
    {
        abort_if(Gate::denies('sale_create'), 403);

        $this->validate([
            'import_file' => [
                'required',
                'file',
            ],
        ]);

        SaleReturn::import(new SaleImport(), $this->file('import_file'));

        $this->alert('success', 'Sales imported successfully');

        $this->emit('refreshIndex');

        $this->importModal = false;
    }

    //  Payment modal

    public function paymentModal(SaleReturn $salereturn)
    {
        abort_if(Gate::denies('sale_access'), 403);

        $this->salereturn = $salereturn;
        $this->date = date('Y-m-d');
        $this->reference = 'ref-'.date('Y-m-d-h');
        $this->amount = $salereturn->due_amount;
        $this->payment_method = 'Cash';
        // $this->note = '';
        $this->salereturn_id = $salereturn->id;
        $this->paymentModal = true;
    }

    public function paymentSave()
    {
        try {
            $this->validate(
                [
                    'date' => 'required|date',
                    'reference' => 'required|string|max:255',
                    'amount' => 'required|numeric',
                    'payment_method' => 'required|string|max:255',
                ]
            );

            $salereturn = SaleReturn::find($this->salereturn_id);

            SaleReturnPayment::create([
                'date' => $this->date,
                'reference' => $this->reference,
                'amount' => $this->amount,
                'note' => $this->note ?? null,
                'sale_id' => $this->salereturn_id,
                'payment_method' => $this->payment_method,
                // 'user_id'        => Auth::user()->id,
            ]);

            $salereturn = SaleReturn::findOrFail($this->salereturn_id);

            $due_amount = $salereturn->due_amount - $this->amount;

            if ($due_amount === $salereturn->total_amount) {
                $payment_status = PaymentStatus::Due;
            } elseif ($due_amount > 0) {
                $payment_status = PaymentStatus::Partial;
            } else {
                $payment_status = PaymentStatus::Paid;
            }

            $salereturn->update([
                'paid_amount' => ($salereturn->paid_amount + $this->amount) * 100,
                'due_amount' => $due_amount * 100,
                'payment_status' => $payment_status,
            ]);

            $this->alert('success', __('Sale Return Payment created successfully.'));

            $this->emit('refreshIndex');

            $this->paymentModal = false;
        } catch (Throwable $th) {
            $this->alert('error', __('Error.').$th->getMessage());
        }
    }

    public function refreshCustomers()
    {
        $this->initListsForFields();
    }

    protected function initListsForFields(): void
    {
        $this->listsForFields['customers'] = Customer::pluck('name', 'id')->toArray();
    }
}
