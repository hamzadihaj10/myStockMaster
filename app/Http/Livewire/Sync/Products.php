<?php

declare(strict_types=1);

namespace App\Http\Livewire\Sync;

use App\Models\Product;
use App\Jobs\SyncCustomProducts;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Jantinnerezo\LivewireAlert\LivewireAlert;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\Response;

class Products extends Component
{
    use LivewireAlert;
    use WithFileUploads;

    /** @var array<string> */
    public $listeners = ['syncModal'];

    public $type;

    public $store_url;

    public $syncModal = false;

    public function updatedType(): void
    {
        if ($this->type === 'woocommerce') {
            $this->store_url = settings()->woocommerce_store_url;
        } elseif ($this->type === 'shopify') {
            $this->store_url = settings()->shopify_store_url;
        } elseif ($this->type === 'custom') {
            $this->store_url = settings()->custom_store_url;
        }
    }

    public function syncModal(): void
    {
        $this->syncModal = true;
    }

    public function sync()
    {

        $inventoryProducts = Product::with('category')->get();

        $client = Http::withHeaders([
            'Authorization' => 'Bearer ' . settings()->custom_api_key,
        ]);

        // Connect to the user's e-commerce store
        if ($this->type === 'woocommerce') {
            $response = new \Automattic\WooCommerce\Client(
                settings()->woocommerce_store_url,
                settings()->woocommerce_api_key,
                settings()->woocommerce_api_secret,
                ['wp_api' => true, 'version' => 'wc/v3']
            );
        } elseif ($this->type === 'shopify') {
            $response = new \Shopify\Client([
                'shop_domain' => settings()->shopify_store_url,
                'api_key' => settings()->shopify_api_key,
                'api_secret' => settings()->shopify_api_secret,
            ]);
        } elseif ($this->type === 'custom') {
            $response = $client->get(settings()->custom_store_url . '/api/products');
        }

        if ($response->getStatusCode() === Response::HTTP_OK) {
            // Retrieve the products from the user's e-commerce store
            $ecomProducts = $response->json()['data'];

            $data = [];

            // Check which products need to be created
            foreach ($inventoryProducts as $product) {
                if (! in_array($product->code, array_column($ecomProducts, 'code'))) {
                    $data[] = [
                        'name' => $product['name'],
                        'code' => $product['code'],
                        'price' => $product['price'],
                        'quantity' => $product['quantity'],
                        'categoryId' => $product['category']->name,
                    ];
                }
            }

            ProductImportJob::dispatch($data);

            try {
            
                $client->post(settings()->custom_store_url . '/api/products/bulk', ['data' => $data]);
            
                Log::info(count($data) . ' new products created in e-commerce store.');
                return response()->json(['message' => count($data) . ' new products created in e-commerce store.']);
            } catch (\Exception $e) {
                Log::warning('Failed to create new products in e-commerce store: ' . $e->getMessage());
                return response()->json(['message' => 'Failed to create new products in e-commerce store.'], 500);
            }
        

        }
    }

    public function render()
    {
        return view('livewire.sync.products');
    }
}
