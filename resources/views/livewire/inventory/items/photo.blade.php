<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\On;
use App\Models\InvItem;
use Carbon\Carbon;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Facades\Storage;

new class extends Component {
    use WithFileUploads;

    public string $size = "md";

    public bool $is_editing = false;

    public int $id = 0;

    public string $photo_url = "";

    public $photo = "";

    public function updatedPhoto()
    {
        $validator = Validator::make(
            ["photo" => $this->photo],
            ["photo" => "nullable|mimetypes:image/jpeg,image/png,image/gif|max:1024"],
            ["mimetypes" => __("Berkas harus jpg, png, atau gif"), "max" => __("Berkas maksimal 1 MB")],
        );

        if ($validator->fails()) {
            $errors = $validator->errors();
            $error = $errors->first("photo");
            $this->js('toast("' . $error . '", { type: "danger" })');
            return;
        }

        $photo_temp = $this->photo ? $this->photo->getFilename() : "";

        if ($this->is_editing) {
            $photo = $this->storePhoto($photo_temp);

            if ($photo) {
                $this->dispatch("photo-updated", photo: $photo);
            }
        } else {
            $item = InvItem::find($this->id);

            if ($item) {
                $store = Gate::inspect("store", $item);

                if ($store->allowed()) {
                    $item->photo = $this->storePhoto($photo_temp);
                    $item->save();

                    $this->js('toast("' . __("Foto diperbarui") . '", { type: "success" })');
                } else {
                    $this->js('toast("' . $store->message() . '", { type: "danger" })');
                }
            }
        }
    }

    private function storePhoto(string $photo_temp)
    {
        $photo = null;
        try {
            $path = storage_path("app/livewire-tmp/" . $photo_temp);
            $manager = new ImageManager(new Driver());
            $image = $manager
                ->read($path)
                ->scale(600, 600)
                ->toJpeg(70);

            $time = Carbon::now()->format("YmdHis");
            $rand = Str::random(5);
            $photo = $time . "_" . $rand . ".jpg";

            $is_stored = Storage::put("/public/inv-items/" . $photo, $image);

            if (! $is_stored) {
                $photo = null;
            }

            $this->photo_url = "/storage/inv-items/" . $photo;
        } catch (\Exception $e) {
            $this->js('toast("' . $e->getMessage() . '", { type: "danger" })');
        }

        return $photo;
    }

    #[On("remove-photo")]
    public function removePhoto()
    {
        $this->reset(["photo_url", "photo"]);
        $this->dispatch("photo-updated", photo: "");
    }
};

?>

<div x-data="{ dropping: false }">
    <div
        class="relative rounded-none h-48 mx-0 sm:mx-auto {{ $size === "md" ? "sm:w-48 md:w-72 md:h-72 lg:w-80 lg:h-80" : "" }} bg-neutral-200 dark:bg-neutral-700 sm:rounded-md overflow-hidden"
        x-on:dragover.prevent="dropping = true"
    >
        <div wire:key="ph" class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2">
            <svg xmlns="http://www.w3.org/2000/svg" class="block h-32 w-auto fill-current text-neutral-800 dark:text-neutral-200 opacity-25" viewBox="0 0 38.777 39.793">
                <path
                    d="M19.396.011a1.058 1.058 0 0 0-.297.087L6.506 5.885a1.058 1.058 0 0 0 .885 1.924l12.14-5.581 15.25 7.328-15.242 6.895L1.49 8.42A1.058 1.058 0 0 0 0 9.386v20.717a1.058 1.058 0 0 0 .609.957l18.381 8.633a1.058 1.058 0 0 0 .897 0l18.279-8.529a1.058 1.058 0 0 0 .611-.959V9.793a1.058 1.058 0 0 0-.599-.953L20 .105a1.058 1.058 0 0 0-.604-.095zM2.117 11.016l16.994 7.562a1.058 1.058 0 0 0 .867-.002l16.682-7.547v18.502L20.6 37.026V22.893a1.059 1.059 0 1 0-2.117 0v14.224L2.117 29.432z"
                />
            </svg>
        </div>
        @if ($photo_url)
            <img class="absolute w-full h-full object-cover dark:brightness-75 top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2" src="{{ $photo_url }}" />
        @endif

        <div
            wire:loading.class="hidden"
            class="absolute w-full h-full top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white/80 dark:bg-neutral-800/80 p-3"
            x-cloak
            x-show="dropping"
        >
            <div class="flex justify-around items-center w-full h-full border-dashed border-2 border-neutral-500 text-neutral-500 dark:text-neutral-400 rounded">
                <div class="text-center">
                    <div class="text-4xl mb-3">
                        <i class="icon-upload"></i>
                    </div>
                    <div>
                        {{ __("Jatuhkan untuk mengunggah") }}
                    </div>
                </div>
            </div>
        </div>
        <input
            wire:model="photo"
            x-ref="invItemPhoto"
            type="file"
            accept="image/*"
            class="absolute inset-0 z-50 m-0 p-0 w-full h-full outline-none opacity-0"
            x-cloak
            x-show="dropping"
            x-on:dragover.prevent="dropping = true"
            x-on:dragleave.prevent="dropping = false"
            x-on:drop="dropping = false"
        />
        <div
            wire:loading.class.remove="hidden"
            wire:target="photo"
            class="hidden absolute w-full h-full top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white dark:bg-neutral-800 opacity-80"
        >
            <x-spinner />
        </div>
    </div>
    <div wire:key="tools">
        @if ($is_editing)
            <div class="p-4 text-sm text-neutral-600 dark:text-neutral-400">
                <div wire:key="discard">
                    @if ($photo_url)
                        <div class="mb-4">
                            <x-text-button type="button" wire:click="removePhoto">
                                <i class="icon-x mr-3"></i>
                                {{ __("Buang foto") }}
                            </x-text-button>
                        </div>
                    @endif
                </div>
                <div>
                    <x-text-button type="button" x-on:click="$refs.invItemPhoto.click()">
                        <i class="icon-upload mr-3"></i>
                        {{ __("Unggah foto") }}
                    </x-text-button>
                </div>
            </div>
        @endif
    </div>
</div>
