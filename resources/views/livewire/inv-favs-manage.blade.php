<div class="p-6">
    <div class="flex justify-between locs-start">
        <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
            {{ __('Lokasi dan tag favorit') }}
        </h2>
        <x-text-button type="button" x-on:click="$dispatch('close')"><i class="fa fa-times"></i></x-text-button>
    </div>
    <form wire:submit="save" x-data="{
        locs: [],
        tags: [],
        qloc: '',
        qtag: '',
        qlocE: false,
        qtagE: false,
        addLoc() {
            if (this.isValidInput(this.qloc)) {
                this.qlocE = false;
                this.locs.unshift(this.qloc.toUpperCase());
                this.qloc = '';
            } else {
                this.qlocE = true;
            }
        },
        addTag() {
            if (this.isValidInput(this.qtag)) {
                this.qtagE = false;
                this.tags.unshift(this.qtag);
                this.qtag = '';
            } else {
                this.qtagE = true;
            }
        },
        removeLoc(index) {
            this.locs.splice(index, 1);
        },
        removeTag(index) {
            this.tags.splice(index, 1);
        },
        isValidInput(input) {
            const regex = /^[a-zA-Z0-9-_]+$/;
            return regex.test(input);
        }
    }" >
        <div class="mt-6">
            <div class="flex gap-x-2">
                <div class="w-full">
                    <x-text-input-icon x-model="qloc" @keydown.enter.prevent="addLoc" icon="fa fa-fw fa-map-marker-alt"
                        id="inv-loc" type="text" placeholder="{{ __('Tambah lokasi') }}" />
                </div>
                <x-secondary-button x-on:click="addLoc"><i class="fa fa-plus"></i></x-secondary-button>
            </div>
            <div x-show="qlocE" class="text-red-500 mt-2">
                {{ __('Hanya menerima huruf, angka, dan strip') }}
            </div>
            <table class="table table-sm mt-2">
                <tr class="text-neutral-400 dark:text-neutral-600" x-show="locs.length == 0">
                    <td>{{ __('Tak ada lokasi favorit') }}
                    </td>
                </tr>     
                <template x-for="(loc, index) in locs" :key="index">
                    <tr tabindex="0" x-on:click="removeLoc(index)">
                        <td>
                            <i class="fa fa-fw fa-map-marker-alt mr-2"></i>
                            <span x-text="loc"></span>
                        </td>
                        <td class="text-right">
                            <i class="reveal fa fa-times"></i>
                        </td>
                    </tr>
                </template>
            </table>
        </div>
        <div class="mt-6">
            <div class="flex gap-x-2">
                <div class="w-full">
                    <x-text-input-icon x-model="qtag" @keydown.enter.prevent="addTag" icon="fa fa-fw fa-tag" id="inv-tag"
                        type="text" placeholder="{{ __('Tambah tag') }}" />
                </div>
                <x-secondary-button x-on:click="addTag"><i class="fa fa-plus"></i></x-secondary-button>
            </div>
            <div x-show="qtagE" class="text-red-500 mt-2">
                {{ __('Hanya menerima huruf, angka, dan strip') }}
            </div>
            <table class="table table-sm mt-2">
                <tr class="text-neutral-400 dark:text-neutral-600" x-show="tags.length == 0">
                    <td>{{ __('Tak ada tag favorit') }}
                    </td>
                </tr>                
                <template x-for="(tag, index) in tags" :key="index">
                    <tr tabindex="0" x-on:click="removeTag(index)">
                        <td>
                            <i class="fa fa-fw fa-tag mr-2"></i>
                            <span x-text="tag"></span>
                        </td>
                        <td class="text-right">
                            <i class="reveal fa fa-times"></i>
                        </td>
                    </tr>
                </template>
            </table>
        </div>
        <div class="flex justify-end mt-6">
            <x-primary-button type="submit">{{ __('Simpan') }}</x-primary-button>
        </div>
    </form>
    <x-spinner-bg wire:loading.class.remove="hidden"></x-spinner-bg>
    <x-spinner wire:loading.class.remove="hidden" class="hidden"></x-spinner>
</div>
