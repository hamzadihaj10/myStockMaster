<?php

declare(strict_types=1);

namespace App\Http\Livewire\Purchase;

use App\Enums\PaymentStatus;
use App\Enums\PurchaseStatus;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\PurchasePayment;
use App\Models\Supplier;
use Carbon\Carbon;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Support\Facades\Auth;
use Jantinnerezo\LivewireAlert\LivewireAlert;
use Livewire\Component;

class Create extends Component
{
    use LivewireAlert;

    /** @var array<string> */
    public $listeners = ['productSelected', 'refreshIndex'];

    public $cart_instance;

    public $suppliers;

    public $products;

    public $supplier_id;

    public $supplier;

    public $product;

    public $quantity;

    public $reference;

    public $total_amount;

    public $check_quantity;

    public $price;

    public $tax_percentage;

    public $discount_percentage;

    public $shipping_amount;

    public $shipping;

    public $paid_amount;

    public $note;

    public $status;

    public $payment_method;

    public $date;

    public $listsForFields = [];

    public function rules(): array
    {
        return [
            'supplier_id' => 'required|numeric',
            'reference' => 'required|string|max:255',
            'tax_percentage' => 'required|integer|min:0|max:100',
            'discount_percentage' => 'required|integer|min:0|max:100',
            'shipping_amount' => 'required|numeric',
            'total_amount' => 'required|numeric',
            'paid_amount' => 'required|numeric',
            'status' => 'required|int|max:255',
            'payment_method' => 'required|string|max:255',
            'note' => 'nullable|string|max:1000',
        ];
    }

    public function mount($cartInstance): void
    {
        $this->cart_instance = $cartInstance;

        $this->reference = settings()->purchase_prefix.'-'.date('Y-m-d-h');
        $this->tax_percentage = 0;
        $this->discount_percentage = 0;
        $this->shipping_amount = 0;
        $this->paid_amount = 0;
        $this->payment_method = 'cash';
        $this->date = Carbon::today()->format('Y-m-d');

        $this->initListsForFields();
    }

    public function render()
    {
        $cart_items = Cart::instance($this->cart_instance)->content();

        return view('livewire.purchase.create', [
            'cart_items' => $cart_items,
        ]);
    }

    public function hydrate(): void
    {
        $this->total_amount = $this->calculateTotal();
        // $this->updatedCustomerId();
    }

    public function save()
    {
        $this->validate();

        $due_amount = $this->total_amount - $this->paid_amount;

        if ($due_amount === $this->total_amount) {
            $payment_status = PaymentStatus::PENDING;
        } elseif ($due_amount > 0) {
            $payment_status = PaymentStatus::PARTIAL;
        } else {
            $payment_status = PaymentStatus::PAID;
        }

        $purchase = Purchase::create([
            'reference' => settings()->purchase_prefix.'-'.date('Y-m-d-h'),
            'date' => $this->date,
            'supplier_id' => $this->supplier_id,
            'user_id' => Auth::user()->id,
            'tax_percentage' => $this->tax_percentage,
            'discount_percentage' => $this->discount_percentage,
            'shipping_amount' => $this->shipping_amount * 100,
            'paid_amount' => $this->paid_amount * 100,
            'total_amount' => $this->total_amount * 100,
            'due_amount' => $due_amount * 100,
            'status' => 2,
            'payment_status' => $payment_status,
            'payment_method' => $this->payment_method,
            'note' => $this->note,
            'tax_amount' => Cart::instance('purchase')->tax() * 100,
            'discount_amount' => Cart::instance('purchase')->discount() * 100,
        ]);

        foreach (Cart::instance('purchase')->content() as $cart_item) {
            PurchaseDetail::create([
                'purchase_id' => $purchase->id,
                'product_id' => $cart_item->id,
                'name' => $cart_item->name,
                'code' => $cart_item->options->code,
                'quantity' => $cart_item->qty,
                'price' => $cart_item->price * 100,
                'unit_price' => $cart_item->options->unit_price * 100,
                'sub_total' => $cart_item->options->sub_total * 100,
                'product_discount_amount' => $cart_item->options->product_discount * 100,
                'product_discount_type' => $cart_item->options->product_discount_type,
                'product_tax_amount' => $cart_item->options->product_tax * 100,
            ]);

            if ($this->status === PurchaseStatus::PENDING) {
                $product = Product::findOrFail($cart_item->id);
                $product->update([
                    'quantity' => $product->quantity + $cart_item->qty,
                ]);
            }
        }

        Cart::instance('purchase')->destroy();

        if ($purchase->paid_amount > 0) {
            PurchasePayment::create([
                'date' => $this->date,
                'reference' => settings()->purchase_prefix.'-'.date('Y-m-d-h'),
                'amount' => $purchase->paid_amount,
                'purchase_id' => $purchase->id,
                'payment_method' => $this->payment_method,
            ]);
        }

        $this->alert('success', __('Purchase created successfully!'));

        return redirect()->route('purchases.index');
    }

    public function calculateTotal(): mixed
    {
        return Cart::instance($this->cart_instance)->total() + $this->shipping_amount;
    }

    public function resetCart(): void
    {
        Cart::instance($this->cart_instance)->destroy();
    }

    public function productSelected($product): void
    {
        $cart = Cart::instance($this->cart_instance);

        $exists = $cart->search(function ($cartItem, $rowId) use ($product) {
            return $cartItem->id === $product['id'];
        });

        if ($exists->isNotEmpty()) {
            $this->alert('error', __('Product already added to cart!'));

            return;
        }

        $cart->add([
            'id' => $product['id'],
            'name' => $product['name'],
            'qty' => 1,
            'price' => $this->calculate($product)['price'],
            'weight' => 1,
            'options' => [
                'product_discount' => 0.00,
                'product_discount_type' => 'fixed',
                'sub_total' => $this->calculate($product)['sub_total'],
                'code' => $product['code'],
                'stock' => $product['quantity'],
                'unit' => $product['unit'],
                'product_tax' => $this->calculate($product)['product_tax'],
                'unit_price' => $this->calculate($product)['unit_price'],
            ],
        ]);

        $this->check_quantity[$product['id']] = $product['quantity'];
        $this->quantity[$product['id']] = 1;
        $this->discount_type[$product['id']] = 'fixed';
        $this->item_discount[$product['id']] = 0;
        $this->total_amount = $this->calculateTotal();
    }

    public function removeItem($row_id): void
    {
        Cart::instance($this->cart_instance)->remove($row_id);
    }

    public function updateQuantity($row_id, $product_id): void
    {
        if ($this->check_quantity[$product_id] < $this->quantity[$product_id]) {
            $this->alert('error', __('Quantity is greater than stock!'));

            return;
        }

        Cart::instance($this->cart_instance)->update($row_id, $this->quantity[$product_id]);

        $cart_item = Cart::instance($this->cart_instance)->get($row_id);

        Cart::instance($this->cart_instance)->update($row_id, [
            'options' => [
                'sub_total' => $cart_item->price * $cart_item->qty,
                'code' => $cart_item->options->code,
                'stock' => $cart_item->options->stock,
                'unit' => $cart_item->options->unit,
                'product_tax' => $cart_item->options->product_tax,
                'unit_price' => $cart_item->options->unit_price,
                'product_discount' => $cart_item->options->product_discount,
                'product_discount_type' => $cart_item->options->product_discount_type,
            ],
        ]);
    }

    public function calculate($product): array
    {
        $price = 0;
        $unit_price = 0;
        $product_tax = 0;
        $sub_total = 0;

        if ($product['tax_type'] === 1) {
            $price = $product['price'] + ($product['price'] * $product['order_tax'] / 100);
            $unit_price = $product['price'];
            $product_tax = $product['price'] * $product['order_tax'] / 100;
            $sub_total = $product['price'] + ($product['price'] * $product['order_tax'] / 100);
        } elseif ($product['tax_type'] === 2) {
            $price = $product['price'];
            $unit_price = $product['price'] - ($product['price'] * $product['order_tax'] / 100);
            $product_tax = $product['price'] * $product['order_tax'] / 100;
            $sub_total = $product['price'];
        } else {
            $price = $product['price'];
            $unit_price = $product['price'];
            $product_tax = 0.00;
            $sub_total = $product['price'];
        }

        return ['price' => $price, 'unit_price' => $unit_price, 'product_tax' => $product_tax, 'sub_total' => $sub_total];
    }

    public function updateCartOptions($row_id, $product_id, $cart_item, $discount_amount)
    {
        Cart::instance($this->cart_instance)->update($row_id, [
            'options' => [
                'sub_total' => $cart_item->price * $cart_item->qty,
                'code' => $cart_item->options->code,
                'stock' => $cart_item->options->stock,
                'unit' => $cart_item->options->unit,
                'product_tax' => $cart_item->options->product_tax,
                'unit_price' => $cart_item->options->unit_price,
                'product_discount' => $discount_amount,
                'product_discount_type' => $this->discount_type[$product_id],
            ],
        ]);
    }

    protected function initListsForFields(): void
    {
        $this->listsForFields['suppliers'] = Supplier::pluck('name', 'id')->toArray();
    }
}
