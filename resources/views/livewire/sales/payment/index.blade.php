
<div>
    <x-modal wire:model="showPayments">
        <x-slot name="title">
            <h2 class="text-lg font-medium text-gray-900">
                {{ __('Sale Payment') }}
            </h2>
            <div class="flex justify-end">
                @if($sale)
                <x-button wire:click="$emit('paymentModal', {{ $sale->id}})" primary type="button">
                    {{ __('Add Payment') }}
                </x-button>
                @endif
            </div>
        </x-slot>
        <x-slot name="content">
            <x-table>
                <x-slot name="thead">
                    <x-table.th>{{ __('Date') }}</x-table.th>
                    <x-table.th>{{ __('Amount') }}</x-table.th>
                    <x-table.th>{{ __('Due Amount') }}</x-table.th>
                    <x-table.th>{{ __('Payment Method') }}</x-table.th>
                    <x-table.th>{{ __('Actions') }}</x-table.th>
                </x-slot>
                <x-table.tbody>

                    @forelse ($salepayments as $salepayment)
                        <x-table.tr>
                            <x-table.td>{{ $salepayment->created_at }}</x-table.td>
                            <x-table.td>
                                {{ format_currency($salepayment->amount) }}
                            </x-table.td>
                            <x-table.td>
                                {{ format_currency($salepayment->sale->due_amount) }}
                            </x-table.td>
                            <x-table.td>{{ $salepayment->payment_method }}</x-table.td>
                            <x-table.td>
                                @can('access_sale_payments')
                                <x-button wire:click="$emit('paymentModal', {{$salepayment->id}} )"
                                    type="button" primary>
                                    <i class="fa fa-pen"></i>
                                </x-button>
                                @endcan
                                <x-button wire:click="delete({{ $salepayment->id }})"
                                    class="bg-red-500 hover:bg-red-700">
                                    <i class="fa fa-trash"></i>
                                </x-button>
                            </x-table.td>
                        </x-table.tr>
                    @empty
                        <x-table.tr>
                            <x-table.td colspan="3">{{ __('No data found') }}</x-table.td>
                        </x-table.tr>
                    @endforelse
                </x-table.tbody>
            </x-table>

            <div class="mt-4">
                {{-- {{ $sale->salepayments->links() }} --}}
            </div>
           
        </x-slot>
    </x-modal>

</div>
