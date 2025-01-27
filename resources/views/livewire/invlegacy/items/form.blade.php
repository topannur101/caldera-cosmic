<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Renderless;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

use App\Models\InvUom;
use App\Models\InvArea;
use App\Models\InvCurr;
use App\Models\InvItem;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Query\Builder;

new #[Layout('layouts.app')] 
class extends Component {


    public $curr_main;
    public $curr_sec;
    public $currs;

    public InvItem $inv_item;

    public $id;
    public $name;
    public $desc;

    #[Url] 
    public $code;
    public $price = 0;
    public $price_sec = 0;

    public $loc;
    public $tags = [];
    public $uom;
    public $quoms = [];
    public $qty_main_min = 0;
    public $qty_main_max = 0;
    public $denom = 1;
    public $up = 0;
    public $photo;
    public $url;
    public $is_active;

    #[Url] 
    public $inv_area_id;
    public $inv_curr_id;

    public function rules()
    {
        return [
            'name'          => ['required','min:1', 'max:128'],
            'desc'          => ['required', 'min:1', 'max:256'],
            'code'          => ['nullable', 'alpha_dash', 'size:11', Rule::unique('inv_items')->where(fn (Builder $q) => $q->where('code', $this->code)->where('inv_area_id', $this->inv_area_id))->ignore($this->inv_item->id ?? '')],
            'price'         => ['required', 'numeric', 'min:0', 'max:999000000'],
            'price_sec'     => ['required', 'numeric', 'min:0', 'max:999000000'],
            'uom'           => ['required', 'min:1', 'max:5'],
            'denom'         => ['required', 'integer', 'min:1', 'max:1000'],
            'loc'           => ['nullable', 'alpha_dash', 'max:20'],
            'tags.*'        => ['nullable', 'alpha_dash', 'max:20'],
            'qty_main_min'  => ['required', 'integer', 'min:0', 'max:99999'],
            'qty_main_max'  => ['required', 'integer', 'min:0', 'max:99999'],
            'is_active'     => ['required', 'boolean'],

            'inv_curr_id'   => ['nullable', 'integer', 'exists:App\Models\InvCurr,id'],
            'inv_area_id'   => ['required', 'integer', 'exists:App\Models\InvArea,id'],
        ];
    }

    public function messages()
    {
        return [
            'tags.*.alpha_dash' => __('Tag hanya boleh berisi huruf, angka, dan strip')
        ];
    }


    public function mount(InvItem $inv_item)
    {
        $area = InvArea::find($this->inv_area_id);
        $mode = '';

        if ($inv_item->id) {
            // edit mode fill all properties
            $mode = 'edit';
            $this->fill(
                $inv_item->only('name', 'desc', 'code', 'price', 'inv_area_id', 'inv_curr_id', 'price_sec', 'denom', 'qty_main_min', 'qty_main_max', 'photo', 'is_active')
            );
            //fill uom, loc, tags and up
            $this->uom = $inv_item->inv_uom->name ?? '';
            $this->loc = $inv_item->inv_loc->name ?? '';
            $this->tags = $inv_item->tags_array();
            $this->url = '/storage/inv-items/'.$inv_item->photo;
            $this->curr_sec = InvCurr::find($this->inv_curr_id);
            $this->up = $inv_item->denom() > 1 ? $inv_item->price() : 0;

        } elseif ($area) {
            // create mode needs area_id (required) and inv_code (optional)
            $mode = 'create';
        } 

        if (!$mode) {
            return abort('403', __('Parameter tidak sah'));
        }

        // fill global inventory param
        $this->currs = InvCurr::where('id', '<>', 1)->get(); 
        $this->quoms = InvUom::orderBy('name')->get()->pluck('name')->toArray(); 
        $this->curr_main = InvCurr::find(1);
        
    }

    public function updatedInvCurrId()
    {
        $this->curr_sec = InvCurr::find($this->inv_curr_id);
        $this->updatedPrice();
    }

    public function updatedPrice()
    {
        $rate   = (double)($this->curr_sec->rate ?? 0);
        $price  = (double)$this->price;
        $this->price_sec = round(($rate > 0 ? ($price * $rate) : 0), 2);
    }

    public function updatedPriceSec()
    {
        $rate       = (double)$this->curr_sec->rate ?? 0;
        $price_sec  = (double)$this->price_sec;
        $this->price = round(($rate > 0 ? ($price_sec / $rate) : 0), 2);
        $this->calcUp();
    }

    #[On('loc-applied')] 
    public function updateLoc($loc)
    {
        $this->loc = $loc;
    }

    #[On('tags-applied')] 
    public function updateTags($tags)
    {
        $this->tags = $tags;
    }

    public function calcUp()
    {
        $price = (double)$this->price;
        $denom = (int)$this->denom;
        $uom = $this->uom;

        $this->up = round((($price && $denom > 1 && $uom) ? ($price / $denom) : 0), 2);
    }

    public function updated($property)
    {
        if ($property == 'price' || $property == 'denom' || $property == 'uom') {
            $this->calcUp();
        } 
    }

    #[Renderless] 
    #[On('photo-updated')] 
    public function updatePhoto($photo)
    {
        $this->photo = $photo;
    }

    public function save()
    {
        // validate if photo url exist

        $curr       = InvCurr::find((int)$this->inv_curr_id);
        $rate       = (double)($curr ? $curr->rate : 1);
        $price      = (double)$this->price;
        $price_sec  = (double)$this->price_sec;
        $denom      = (int)$this->denom;

        if($curr && $price_sec && $rate > 0) {
            $this->price     = round(($price_sec / $rate),2);
        } else {
            $this->inv_curr_id  = '';
            $this->price        = $price;
        }

        $this->price_sec = $price_sec; 
        $this->qty_main_min   = (int)$this->qty_main_min;
        $this->qty_main_max   = (int)$this->qty_main_max;
        $this->denom = $denom > 0 ? $denom : 1;

        $props = ['name', 'desc', 'code', 'uom'];
        foreach($props as $prop) {
            $this->$prop = trim($this->$prop);
        }        
      
        $propUps = ['uom', 'code'];
        foreach ($propUps as $propUp) {
            $this->$propUp = strtoupper($this->$propUp);
        }

        $this->code = $this->code ? $this->code : null;
        $this->is_active = $this->is_active !== null ? $this->is_active : false;

        $validated = $this->validate();

        // get uom id, required
        $uom = InvUom::firstOrCreate([
            'name' => $this->uom
        ]);

        $validated['inv_curr_id'] = $this->inv_curr_id ? $this->inv_curr_id : null;
        $validated['inv_uom_id'] = $uom->id;


        if($this->inv_item->id ?? false) {
            Gate::authorize('updateOrCreate', $this->inv_item);
            $this->inv_item->update($validated);
            $msg = __('Barang diperbarui');
        } else {
            $validated['freq'] = 0;
            $validated['qty_main'] = 0;
            $validated['qty_used'] = 0;
            $validated['qty_rep'] = 0;
            $this->inv_item = new InvItem($validated);
            Gate::authorize('updateOrCreate', $this->inv_item);
            $this->inv_item->save();
            $msg = __('Barang dibuat');
        }

        $this->inv_item->updateLoc($this->loc);
        $this->inv_item->updateTags($this->tags);
        $this->inv_item->updatePhoto($this->photo);

        return redirect(route('invlegacy.items.show', ['id' => $this->inv_item->id]))->with('status', $msg);
    }

};

?>

<div class="block sm:flex gap-x-6">
    <livewire:invlegacy.items.photo isForm="true" :url="$inv_item->photo ?? false ? $url : ''" />
    <form wire:submit="save()" class="w-full overflow-hidden">
        <div class="px-4 pb-4">
            <div class="bg-white dark:bg-neutral-800 shadow rounded-lg p-4 mb-4">
                <div class="text-medium text-sm uppercase text-neutral-400 dark:text-neutral-600 mb-4">
                    {{ __('Informasi Dasar') }}</div>
                <x-text-input wire:model="name" type="text" placeholder="{{ __('Nama') }}" />
                <div wire:key="err-name">
                    @error('name')
                        <x-input-error messages="{{ $message }}" class="m-2" />
                    @enderror
                </div>
                <x-text-input wire:model="desc" class="mt-4" type="text" placeholder="{{ __('Deskripsi') }}" />
                <div wire:key="err-desc">
                    @error('desc')
                        <x-input-error messages="{{ $message }}" class="m-2" />
                    @enderror
                </div>
                <x-text-input wire:model="code" class="mt-4" type="text" placeholder="{{ __('Kode') }}" />
                <div wire:key="err-code">
                    @error('code')
                        <x-input-error messages="{{ $message }}" class="m-2" />
                    @enderror
                </div>
                <div x-data="{ is_active: @entangle('is_active') }" class="mt-4">
                    <x-toggle x-model="is_active" :checked="$is_active"><span
                            x-text="is_active ? '{{ __('Aktif') }}' : '{{ __('Nonaktif') }}'"></span></x-toggle>
                </div>
            </div>
            <div class="bg-white dark:bg-neutral-800 shadow rounded-lg p-4 mb-4">
                <div class="text-medium text-sm uppercase text-neutral-400 dark:text-neutral-600 mb-4">
                    {{ __('Harga dan Satuan') }}</div>
                @if ($currs->count())
                    <x-select wire:model.live="inv_curr_id" class="w-full mb-3">
                        <option value="">{{ __('Gunakan hanya') . ' ' . $curr_main->name }}</option>
                        @foreach ($currs as $curr)
                            <option wire:key="{{ 'curr' . $loop->index }}" value="{{ $curr->id }}">
                                {{ __('Dengan') . ' ' . $curr->name }}</option>
                        @endforeach
                    </x-select>
                @endif
                <div wire:key="prices">
                    @if ($curr_sec->name ?? false)
                        <x-text-input-curr wire:model.live="price" readonly id="price" min="0" step=".01"
                            curr="{{ $curr_main->name }}" type="number" placeholder="0" />
                    @else
                        <x-text-input-curr wire:model.live="price" id="price" min="0" step=".01"
                            curr="{{ $curr_main->name }}" type="number" placeholder="0" />
                    @endif
                    <div wire:key="err-price">
                        @error('price')
                            <x-input-error messages="{{ $message }}" class="m-2" />
                        @enderror
                    </div>
                    @if ($curr_sec->name ?? false)
                        <x-text-input-curr wire:model.live="price_sec" id="price-sec" min="0" step=".01"
                            class="mt-4" curr="{{ $curr_sec->name }}" type="number" placeholder="0" />
                        <div wire:key="err-price_sec">
                            @error('price_sec')
                                <x-input-error messages="{{ $message }}" class="m-2" />
                            @enderror
                        </div>
                    @endif
                </div>
                <div class="grid grid-cols-2 gap-x-3 mt-4">
                    <div>
                        <label class="block mb-1 font-medium text-sm text-neutral-700 dark:text-neutral-300"
                            for="uom">
                            {{ __('UOM') }}
                        </label>
                        <x-text-input wire:model.live="uom" id="uom" type="text" list="quoms"></x-text-input>
                        <datalist wire:key="quoms" id="quoms">
                            @if (count($quoms))
                                @foreach ($quoms as $quom)
                                    <option wire:key="{{ 'uom' . $loop->index }}" value="{{ $quom }}">
                                @endforeach
                            @endif
                        </datalist>
                    </div>
                    <div>
                        <label class="block mb-1 font-medium text-sm text-neutral-700 dark:text-neutral-300"
                            for="denom">
                            <span>{{ __('Denominasi') }}</span>
                            <x-text-button type="button" class="ms-1" x-data=""
                                x-on:click.prevent="$dispatch('open-modal', 'inv-denom')"><i
                                    class="far fa-question-circle"></i></x-text-button>
                        </label>
                        <x-text-input wire:model.live="denom" type="number" placeholder="1" id="denom"
                            min="1"></x-text-input>
                        <x-modal name="inv-denom">
                            <div class="p-6">
                                <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                                    {{ __('Denominasi') }}
                                </h2>
                                <p class="mt-3 text-sm text-neutral-600 dark:text-neutral-400">
                                    {{ __('Jika kamu ingin membagi harga utama menjadi satuan yang lebih kecil, isi denominasi sebagai pembagi.') }}
                                </p>
                                <p class="mt-3 text-sm text-neutral-600 dark:text-neutral-400">
                                    {{ __('Contoh: Harga utama USD 100 / PACK. Ada 20 EA setiap PACK, maka isi 20 di denominasi dan EA di UOM.') }}
                                </p>
                                <div class="mt-6 flex justify-end">
                                    <x-secondary-button type="button" x-on:click="$dispatch('close')">
                                        {{ __('Tutup') }}
                                    </x-secondary-button>
                                </div>
                            </div>
                        </x-modal>
                    </div>
                    <div class="col-span-2">
                        <div wire:key="err-uom">
                            @error('uom')
                                <x-input-error messages="{{ $message }}" class="m-2" />
                            @enderror
                        </div>
                        <div wire:key="err-denom">
                            @error('denom')
                                <x-input-error messages="{{ $message }}" class="m-2" />
                            @enderror
                        </div>
                    </div>
                    <div wire:key="up" class="col-span-2 text-center mt-4">
                        @if ($up)
                            <div class="font-medium text-sm text-neutral-700 dark:text-neutral-300">
                                {{ __('Harga per') . ' ' . $uom }}
                            </div>
                            <div class="text-neutral-500">{{ $curr_main->name . ' ' . $up }}</div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="bg-white dark:bg-neutral-800 shadow rounded-lg p-4 mb-4">
                <div class="text-medium text-sm uppercase  text-neutral-400 dark:text-neutral-600 mb-4">
                    {{ __('Lokasi dan Tag') }} — TT MM</div>
                <livewire:invlegacy.items.loc isForm="true" :$loc :$inv_area_id />
                <div wire:key="err-loc">
                    @error('loc')
                        <x-input-error messages="{{ $message }}" class="m-2" />
                    @enderror
                </div>
                <div class="mx-3 mt-3">
                    <x-text-button type="button" class="flex items-center" x-data=""
                        x-on:click.prevent="$dispatch('open-modal', 'inv-item-tags')">
                        <i class="fa fa-fw fa-tag mr-2 text-neutral-400 dark:text-neutral-600"></i>
                        @if (count($tags))
                            <div>{{ implode(', ', $tags) }}</div>
                        @else
                            <div class="text-neutral-500">{{ __('Tak ada tag') }}</div>
                        @endif
                    </x-text-button>
                    <x-modal name="inv-item-tags">
                        <livewire:invlegacy.items.tags isForm="true" :$tags :$inv_area_id lazy />
                    </x-modal>
                </div>
                <div wire:key="err-tags">
                    @if (count($errors->get('tags.*')))
                        <x-input-error messages="{{ current($errors->get('tags.*'))[0] }}" class="m-2" />
                    @endif
                </div>
            </div>
            <div class="bg-white dark:bg-neutral-800 shadow rounded-lg p-4 mb-4">
                <div class="text-medium text-sm uppercase text-neutral-400 dark:text-neutral-600 mb-4">
                    {{ __('Batas qty utama') }}</div>
                <div class="grid grid-cols-2 gap-x-3">
                    <div>
                        <label class="block mb-1 font-medium text-sm text-neutral-700 dark:text-neutral-300"
                            for="qty_main_min">
                            {{ __('Qty minimum') }}
                        </label>
                        <x-text-input wire:model="qty_main_min" id="qty_main_min" type="number" placeholder="0" />
                    </div>
                    <div>
                        <label class="block mb-1 font-medium text-sm text-neutral-700 dark:text-neutral-300"
                            for="qty_main_max">
                            {{ __('Qty maksimum') }}
                        </label>
                        <x-text-input wire:model="qty_main_max" id="qty_main_max" type="number" placeholder="0" />
                    </div>
                    <div class="col-span-2">
                        <div wire:key="err-qty_main_min">
                            @error('qty_main_min')
                                <x-input-error messages="{{ $message }}" class="m-2" />
                            @enderror
                        </div>
                        <div wire:key="err-qty_main_max">
                            @error('qty_main_max')
                                <x-input-error messages="{{ $message }}" class="m-2" />
                            @enderror
                        </div>
                    </div>
                </div>
            </div>
            <input type="hidden" wire:model="photo" />
            <x-primary-button type="submit">{{ __('Simpan') }}</x-primary-button>
        </div>
    </form>
</div>
