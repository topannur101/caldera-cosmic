<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] 
class extends Component {
    public string $userq = '';
    public int $user_id = 0;

    #[Renderless]
    public function updatedUserq()
    {
        $this->dispatch('userq-updated', $this->userq);
    }
};

?>

<x-slot name="title">{{ __('Open Mill Validator') }}</x-slot>

<x-slot name="header">
    <x-nav-insights-omv></x-nav-insights-omv>
</x-slot>

<div id="content" class="pt-8 pb-3 max-w-7xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
    @if (!Auth::user())
        <div class="flex flex-col items-center gap-y-6 px-6 py-20">
            <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl">
                <i class="fa fa-exclamation-circle"></i>
            </div>
            <div class="text-center text-neutral-500 dark:text-neutral-600">
                {{ __('Masuk terlebih dahulu untuk menggunakan timer OMV') }}
            </div>
            <div>
                <a href="{{ route('login', ['redirect' => url()->current()]) }}" wire:navigate
                    class="flex items-center px-6 py-3 mb-3 text-white bg-caldy-600 rounded-md sm:mb-0 hover:bg-caldy-700 sm:w-auto">
                    {{ __('Masuk') }}
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 ml-1" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round">
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                        <polyline points="12 5 19 12 12 19"></polyline>
                    </svg>
                </a>
            </div>
        </div>
    @else
        <div class="flex flex-col sm:flex-row">
            <div class="px-1 py-4">
                <livewire:insight.omv.index-batches />
            </div>
            <div class="w-full overflow-hidden p-4">
                <div class="flex flex-col h-full" x-data="{
                    ...app(),
                    userq: @entangle('userq').live
                }" x-init="loadRecipes();
                fetchLine()">
                <div :class="!timerIsRunning && recipeSelected ? 'cal-glowing z-10' : (timerIsRunning ? 'cal-glow z-10' : '')"
                    :style="'--cal-glow-pos: -' + (timerProgressPosition * 100) + '%'">
                    <div class="bg-white dark:bg-neutral-800 bg-opacity-80 dark:bg-opacity-80 shadow rounded-lg flex w-full items-stretch">
                        <div class="flex justify-between grow mx-6 my-4">
                            <div class="flex flex-col justify-center">
                                <div class="flex items-center gap-x-3">
                                    <div x-show="recipeSelected" x-cloak>
                                        <x-pill class="uppercase"><span class="uppercase" x-text="batchType"></span></x-pill>
                                    </div>
                                    <div class="text-2xl" x-text="recipeSelected ? recipeSelected.name : '{{ __('Menunggu...') }}'"></div>
                                </div>
                                <div class="flex gap-x-3 text-neutral-500">
                                    <div @click="start()"><span>{{ __('Kode') }}</span><span>{{ ': ' }}</span><span
                                        x-text="batchCode ? batchCode.toUpperCase() : '{{ __('Tak ada') }}'"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center">
                                <div class="text-2xl font-mono" x-text="formatTime(timerRemainingTime)" x-show="timerIsRunning"
                                    :class="timerRemainingTime == 0 ? 'text-red-500' : ''"></div>
                            </div>
                        </div>
                        <div class="flex">
                            <div class="px-2 py-4"
                                :class="!batchTeam && timerIsRunning ? 'bg-red-200 dark:bg-red-900 dark:text-white fa-fade' : ''">
                                <label for="batchTeam"
                                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Tim') }}</label>
                                <x-select id="batchTeam" x-model="batchTeam">
                                    <option value=""></option>
                                    <option value="A">A</option>
                                    <option value="B">B</option>
                                    <option value="C">C</option>
                                </x-select>
                            </div>
                            <div class="px-2 py-4 w-48"
                                :class="!userq && timerIsRunning ? 'bg-red-200 dark:bg-red-900 dark:text-white fa-fade' : ''"
                                wire:key="user-select" x-data="{ open: false }"
                                x-on:user-selected="userq = $event.detail; open = false">
                                <div x-on:click.away="open = false">
                                    <label for="omv-user"
                                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Mitra kerja') }}</label>
                                    <x-text-input-icon x-model="userq" icon="fa fa-fw fa-user" x-on:change="open = true"
                                        x-ref="userq" x-on:focus="open = true" id="omv-user" type="text"
                                        autocomplete="off" placeholder="{{ __('Pengguna') }}" />
                                    <div class="relative" x-show="open" x-cloak>
                                        <div class="absolute top-1 left-0 w-full z-10">
                                            <livewire:layout.user-select />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <x-primary-button class="m-4" type="button" size="lg" @click="openWizard()"
                            x-show="!timerIsRunning && !recipeSelected"><i
                                class="fa fa-play mr-2"></i>{{ __('Mulai') }}</x-primary-button>
                        <x-primary-button class="m-4" type="button" size="lg" @click="resetRecipeSelection()"
                            x-show="!timerIsRunning && recipeSelected"><i
                                class="fa fa-undo mr-2"></i>{{ __('Ulangi') }}</x-primary-button>
                        <x-primary-button class="m-4" type="button" size="lg" @click="stop(true, true)" x-cloak
                            x-show="timerIsRunning"><i class="fa fa-stop mr-2"></i>{{ __('Stop') }}</x-primary-button>
                    </div>
                </div>
    
                <x-modal name="omv-worker-unavailable" maxWidth="sm">
                    <div class="text-center pt-6">
                        <i class="fa fa-exclamation-triangle text-4xl "></i>
                        <h2 class="mt-3 text-lg font-medium text-neutral-900 dark:text-neutral-100">
                            {{ __('omv-worker tidak merespon') }}
                        </h2>
                    </div>
                    <div class="p-6">
                        <p class="mt-3 text-sm text-neutral-600 dark:text-neutral-400">
                            {{ __('Caldera perlu bersandingan dengan aplikasi omv-worker untuk berkomunikasi dengan sensor dan kamera.') }}
                        </p>
                        <p class="mt-3 text-sm text-neutral-600 dark:text-neutral-400">
                            {{ __('Kamu dapat mengabaikan pesan ini dan lanjut menggunakan aplikasi, namun data tidak akan terkirim ke server.') }}
                        </p>
                        <p class="mt-3 text-sm text-neutral-600 dark:text-neutral-400">
                            {{ __('Hubungi penanggung jawab/PIC Caldera untuk memperbaiki masalah ini.') }}
                        </p>
                        <div class="mt-6 flex justify-end">
                            <x-primary-button type="button" x-on:click="$dispatch('close')">
                                {{ __('Oke') }}
                            </x-primary-button>
                        </div>
                    </div>
                </x-modal>

                <x-modal name="input-incomplete" maxWidth="sm">
                    <div class="text-center pt-6">
                        <i class="fa fa-exclamation-triangle text-4xl "></i>
                        <h2 class="mt-3 text-lg font-medium text-neutral-900 dark:text-neutral-100">
                            {{ __('Tim atau Mitra kerja masih kosong') }}
                        </h2>
                    </div>
                    <div class="p-6">
                        <p class="mt-3 text-sm text-neutral-600 dark:text-neutral-400">
                            {{ __('Harap lengkapi Tim dan Mitra kerja sebelum timer berhenti agar data sah dan dapat tersimpan.') }}
                        </p>
                        <p class="mt-3 text-sm text-neutral-600 dark:text-neutral-400">
                            {{ __('Kelalaian dalam melengkapi informasi tersebut dapat menyebabkan datamu diabaikan oleh sistem.') }}
                        </p>
                        <div class="mt-6 flex justify-end">
                            <x-primary-button type="button" x-on:click="$dispatch('close')">
                                {{ __('Paham') }}
                            </x-primary-button>
                        </div>
                    </div>
                </x-modal>
    
                <x-modal name="recipes" focusable>
                    <div class="p-6">
                        <!-- Step 1: Batch identity -->
                        <div x-show="wizardCurrentStep === 1">
                            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                                {{ __('Identitas batch') }}
                            </h2>
                            <div class="mt-6">
                                <label for="batchCode"
                                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Kode') }}</label>
                                <x-text-input id="batchCode" x-model="batchCode" type="text" @keydown.enter="nextStep" />
                            </div>
                        </div>
                        <!-- Step 2: Mixing Type -->
                        <div x-show="wizardCurrentStep === 2">
                            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                                {{ __('Pilih tipe mixing') }}
                            </h2>
                            <fieldset class="grid gap-2 mt-6">
                                <div>
                                    <input type="radio" name="batchType" id="batchTypeNew"
                                        class="peer hidden [&:checked_+_label_svg]:block" value="new"
                                        x-model="batchType" />
                                    <label for="batchTypeNew" @click="setTimeout(() => { nextStep() }, 200);"
                                        class="block h-full cursor-pointer rounded-lg border border-neutral-200 dark:border-neutral-700 p-4 hover:border-neutral-300 dark:hover:border-neutral-700 peer-checked:border-caldy-500 peer-checked:ring-1 peer-checked:ring-caldy-500">
                                        <div class="flex items-center justify-between">
                                            <p>{{ __('Baru') }}</p>
                                            <svg class="hidden h-6 w-6 text-caldy-600" xmlns="http://www.w3.org/2000/svg"
                                                viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd"
                                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                    </label>
                                </div>
                                <div>
                                    <input type="radio" name="batchType" id="batchTypeRemixing"
                                        class="peer hidden [&:checked_+_label_svg]:block" value="remixing"
                                        x-model="batchType" />
                                    <label for="batchTypeRemixing" @click="setTimeout(() => { nextStep() }, 200);"
                                        class="block h-full cursor-pointer rounded-lg border border-neutral-200 dark:border-neutral-700 p-4 hover:border-neutral-300 dark:hover:border-neutral-700 peer-checked:border-caldy-500 peer-checked:ring-1 peer-checked:ring-caldy-500">
                                        <div class="flex items-center justify-between">
                                            <p>{{ __('Remixing') }}</p>
                                            <svg class="hidden h-6 w-6 text-caldy-600" xmlns="http://www.w3.org/2000/svg"
                                                viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd"
                                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                    </label>
                                </div>
                                <div>
                                    <input type="radio" name="batchType" id="batchTypeScrap"
                                        class="peer hidden [&:checked_+_label_svg]:block" value="scrap"
                                        x-model="batchType" />
                                    <label for="batchTypeScrap" @click="setTimeout(() => { nextStep() }, 200);"
                                        class="block h-full cursor-pointer rounded-lg border border-neutral-200 dark:border-neutral-700 p-4 hover:border-neutral-300 dark:hover:border-neutral-700 peer-checked:border-caldy-500 peer-checked:ring-1 peer-checked:ring-caldy-500">
                                        <div class="flex items-center justify-between">
                                            <p>{{ __('Scrap') }}</p>
                                            <svg class="hidden h-6 w-6 text-caldy-600" xmlns="http://www.w3.org/2000/svg"
                                                viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd"
                                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                    </label>
                                </div>
                            </fieldset>
                        </div>
    
                        <!-- Step 3: Recipe Selection -->
                        <div x-show="wizardCurrentStep === 3">
                            <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                                {{ __('Pilih resep') }}
                            </h2>
                            <fieldset class="grid gap-2 mt-6 max-h-96 overflow-y-scroll p-1">
                                <template x-if="recipeFilteredList.length > 0">
                                    <template x-for="recipe in recipeFilteredList" :key="recipe.id">
                                        <div>
                                            <input type="radio" name="recipe" :id="'recipe-' + recipe.id"
                                                class="peer hidden [&:checked_+_label_svg]:block" :value="recipe.id"
                                                x-model="recipeSelectedId" />
                                            <label :for="'recipe-' + recipe.id"
                                                class="block h-full cursor-pointer rounded-lg border border-neutral-200 dark:border-neutral-700 p-4 hover:border-neutral-300 dark:hover:border-neutral-700 peer-checked:border-caldy-500 peer-checked:ring-1 peer-checked:ring-caldy-500">
                                                <div class="flex items-center justify-between">
                                                    <p x-text="recipe.name"></p>
                                                    <svg class="hidden h-6 w-6 text-caldy-600"
                                                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                                                        fill="currentColor">
                                                        <path fill-rule="evenodd"
                                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                            clip-rule="evenodd" />
                                                    </svg>
                                                </div>
                                            </label>
                                        </div>
                                    </template>
                                </template>
                                <template x-if="recipeFilteredList.length === 0">
                                    <div class="text-center text-neutral-500">
                                        {{ __('Tidak ada resep untuk tipe ini') }}
                                    </div>
                                </template>
                            </fieldset>
                        </div>
    
                        <!-- Navigation buttons -->
                        <div class="flex mt-8 justify-end gap-x-3">
                            <x-secondary-button type="button" x-show="wizardCurrentStep > 1" @click="prevStep">
                                {{ __('Mundur') }}
                            </x-secondary-button>
                            <x-secondary-button type="button" x-show="wizardCurrentStep < 3" @click="nextStep">
                                {{ __('Maju') }}
                            </x-secondary-button>
                            <x-primary-button type="button" x-show="wizardCurrentStep === 3" @click="finishWizard">
                                {{ __('Terapkan') }}
                            </x-primary-button>
                        </div>
                    </div>
                </x-modal>
    
                <div x-show="!recipeSelected" class="grow mt-6">
                    <div class="bg-white dark:bg-neutral-800 bg-opacity-80 dark:bg-opacity-80 shadow rounded-lg h-full flex items-center">
                        <div class="grow py-20">
                            <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                                <i class="fa fa-flask relative"><i
                                        class="fa fa-question-circle absolute bottom-0 -right-1 text-lg text-neutral-500 dark:text-neutral-400"></i></i>
                            </div>
                            <div class="text-center text-neutral-400 dark:text-neutral-600">{{ __('Belum ada resep yang dipilih') }}
                            </div>
                        </div>
                    </div>
                </div>
    
                <div x-show="recipeSelected" class="grid grid-cols-2 gap-3 mt-6">
                    <template x-for="(step, index) in stepList" :key="index">
                        <div class="bg-white dark:bg-neutral-800 shadow rounded-lg p-4"
                            :class="stepCurrentIndex == index && timerIsRunning ? 'cal-shimmer' : ''">
                            <div class="flex gap-4  w-full mb-6">
                                <div class="w-12 h-12 rounded-full flex items-center justify-center"
                                    :class="(stepCurrentIndex > index && timerIsRunning) ? 'bg-green-500 text-neutral-800' : ((
                                            stepCurrentIndex == index && timerIsRunning) ? 'bg-yellow-500 text-neutral-800' :
                                        'bg-neutral-800 dark:bg-neutral-200 text-white dark:text-neutral-800')">
                                    <span class="text-2xl font-bold" x-text="index + 1"></span>
                                </div>
                                <div class="grow">
                                    <div class="flex justify-between items-center mb-2">
                                        <div class="flex gap-x-3"
                                            :class="stepCurrentIndex == index && timerIsRunning ? 'fa-fade' : ''">
                                            <i x-show="stepCurrentIndex == index && timerIsRunning"
                                                class="fa-solid fa-spinner fa-spin-pulse"></i>
                                            <span class="text-xs uppercase"
                                                x-text="(stepCurrentIndex > index && timerIsRunning) ? '{{ __('Selesai') }}' : ((stepCurrentIndex == index && timerIsRunning) ? '{{ __('Berjalan') }}' : '{{ __('Menunggu') }}')"></span>
                                        </div>
                                        <span class="text-xs font-mono"
                                            x-text="formatTime(stepRemainingTimes[index])"></span>
                                    </div>
                                    <div class="relative w-full bg-gray-200 rounded-full h-1.5 dark:bg-gray-700">
                                        <div class="bg-caldy-600 h-1.5 rounded-full dark:bg-caldy-500 transition-all duration-200"
                                            :style="'width: ' + stepProgressPercentages[index] + '%'"></div>
                                        <!-- Capture points -->
                                        <template
                                            x-for="point in recipeCapturePoints.filter(p => p >= getTotalPreviousStepsDuration(index) && p < getTotalPreviousStepsDuration(index + 1))"
                                            :key="point">
                                            <div class="absolute w-2 h-2 bg-caldy-500 rounded-full top-4 transform -translate-y-1/2"
                                                :style="'left: ' + ((point - getTotalPreviousStepsDuration(index)) / step.duration *
                                                    100) + '%'"
                                                :class="timerElapsedSeconds >= point ? 'opacity-30' : ''"></div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <span class="text-2xl" x-text="step.description"></span><span class="opacity-30"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
            </div>
        </div>
        <script>
            function app() {
                return {
                    batchCode: '',
                    batchEval: '',
                    batchLine: '',
                    batchTeam: '',
                    batchType: '',
                    batchAmps: [],
                    batchImages: [],
                    batchStartTime: null,
                    
                    evalTolerance: 120,
                    
                    overtimeIsActive: false,
                    overtimeElapsed: 0,
                    overtimeMaxDuration: 900,

                    pollingAId: null,
                    pollingBId: null,
                    
                    recipeFilteredList: [],
                    recipeList: [],
                    recipeSelected: null,
                    recipeSelectedDuration: 0,
                    recipeSelectedId: null,

                    recipeCapturePoints: [],
                    recipeCapturePointsDone: [],
                    recipeCaptureThreshold: 1,
                    
                    stepCurrentIndex: 0,
                    stepList: [],
                    stepProgressPercentages: [],
                    stepRemainingTimes: [],
                    
                    timerElapsedSeconds: 0,
                    timerIntervalId: null,
                    timerIsRunning: false,
                    timerProgressPosition: 0, // glow
                    timerRemainingTime: 0,

                    wizardCurrentStep: 1,

                    async fetchLine() {
                        if (!this.batchLine) {
                            try {
                                const response = await fetch('http://127.0.0.1:92/get-line');
                                if (!response.ok) {
                                    throw new Error('Failed to get line');
                                }
                                this.batchLine = await response.text();
                                this.$wire.dispatch('line-fetched', {line: this.batchLine});
                            } catch (error) {
                                console.error('Failed to fetch line:', error);
                            }
                        }
                    },

                    async loadRecipes() {
                        try {
                            const response = await fetch('/api/omv-recipes');
                            this.recipeList = await response.json();
                        } catch (error) {
                            console.error('Failed to load recipes:', error);
                            this.recipeList = [];
                        }
                        //     {
                        //         "id": 1,
                        //         "type": "new", // new property
                        //         "name": "Simple Recipe",
                        //         "capture_points": [5, 70],
                        //         "steps": [
                        //         {
                        //             "description": "Should be description of step 1 recipe",
                        //             "duration": "50"
                        //         },
                        //         {
                        //             "description": "Should be description of step 2 recipe",
                        //             "duration": "60"
                        //         }
                        //         ]
                        //     }, so on

                    },

                    resetRecipeSelection() {
                        this.recipeSelectedId = null;
                        this.recipeSelected = null;
                        this.stepList = [];
                        this.batchCode = '';
                        this.batchType = '';
                        this.recipeFilteredList = [];
                    },

                    applySelectedRecipe() {
                        const selectedRecipe = this.recipeList.find(r => r.id == this.recipeSelectedId);
                        if (selectedRecipe) {
                            this.recipeSelected = selectedRecipe;
                            this.stepList = selectedRecipe.steps;
                            this.recipeCapturePoints = selectedRecipe.capture_points || [];
                            this.calculateTotalDuration();
                            this.reset(false);
                            this.$dispatch('close');
                            this.startPollingA();

                            if (!this.batchLine) {
                                this.$dispatch('open-modal', 'omv-worker-unavailable');
                            }
                        }
                    },

                    calculateTotalDuration() {
                        this.recipeSelectedDuration = this.stepList.reduce((sum, step) => {
                            const duration = Number(step.duration);
                            return sum + (isNaN(duration) ? 0 : duration);
                        }, 0);

                        // Subtract 1 from the total duration, ensuring it doesn't go below 0
                        this.recipeSelectedDuration = Math.max(0, this.recipeSelectedDuration - 1);
                    },

                    startPollingA() {
                        if (this.pollingAId) {
                            clearInterval(this.pollingAId);
                        }

                        this.pollingAId = setInterval(() => {
                            this.fetchLine();
                            fetch('http://127.0.0.1:92/get-data')
                                .then(response => response.json())
                                .then(data => {
                                    console.log('Polling A:', data);
                                    if (data.error) {
                                        console.error('Polling A server error:', data.error);
                                    } else if (data.eval && !this.timerIsRunning) {
                                        this.start();
                                        clearInterval(this.pollingAId);
                                    }
                                })
                                .catch(error => {
                                    console.error('Polling A error:', error);
                                });
                        }, 4000);
                    },

                    nextStep() {
                        if (this.wizardCurrentStep < 3) {
                            this.wizardCurrentStep++;
                            if (this.wizardCurrentStep === 3) {
                                this.filterRecipes();
                            }
                        }
                    },

                    prevStep() {
                        if (this.wizardCurrentStep > 1) {
                            this.wizardCurrentStep--;
                        }
                    },

                    finishWizard() {
                        if (this.batchType && this.recipeSelectedId) {
                            this.applySelectedRecipe();
                        } else {
                            notyfError('{{ __('Tipe mixing dan resep wajib dipilih') }}');
                        }
                    },

                    openWizard() {
                        if (this.timerIsRunning) {
                            notyfError('{{ __('Hentikan timer sebelum memilih resep baru.') }}');
                            return;
                        }
                        this.wizardCurrentStep = 1;
                        this.batchType = '';
                        this.recipeSelectedId = null;
                        this.$dispatch('open-modal', 'recipes')
                    },

                    // Add this new method to filter recipes based on type
                    filterRecipes() {
                        this.recipeFilteredList = this.recipeList.filter(recipe => recipe.type === this.batchType);
                    },

                    modifyClass(id, action, className) {
                        const element = document.getElementById(id);
                        if (element && element.classList) {
                            if (action === 'remove') {
                                element.classList.remove(className);
                            } else if (action === 'add') {
                                element.classList.add(className);
                            }
                        }
                    },

                    start() {
                        if (!this.timerIsRunning && this.stepList.length > 0) {
                            this.timerIsRunning = true;
                            this.batchStartTime = new Date(); // Change this to store the full date object
                            this.timerElapsedSeconds = 0;
                            this.timerRemainingTime = this.recipeSelectedDuration;
                            this.recipeCapturePointsDone = []; // Reset processed capture points
                            this.batchImages = []; // Reset captured images
                            this.tick();

                            if (!this.batchTeam || !this.userq) {
                                this.$dispatch('open-modal', 'input-incomplete');
                            }

                            if (this.pollingAId) {
                                clearInterval(this.pollingAId);
                                this.pollingAId = null;
                            }

                            this.startPollingB();

                            this.modifyClass('cal-nav-main-links', 'remove', 'sm:flex');
                            this.modifyClass('cal-nav-omv', 'add', 'hidden');
                            this.modifyClass('cal-nav-main-links-alt', 'remove', 'hidden');

                        } else if (this.stepList.length === 0) {
                            notyfError('{{ __('Pilih resep terlebih dahulu sebelum menjalankan timer.') }}');
                        }
                    },

                    startPollingB() {
                        this.pollingBId = setInterval(() => {
                            this.fetchLine();
                            fetch('http://127.0.0.1:92/get-data')
                                .then(response => response.json())
                                .then(data => {
                                    console.log('Polling B:', data);
                                    if (data.error) {
                                        console.error('Polling B server error:', data.error);
                                    } else {
                                        this.batchAmps.push({
                                            taken_at: this.timerElapsedSeconds,
                                            value: data.raw
                                        });
                                    }
                                })
                                .catch(error => {
                                    console.error('Polling B error:', error);
                                });
                        }, 4000);
                    },                    

                    stop(resetRecipe = true, sendData = false) {
                        this.timerIsRunning = false;
                        this.modifyClass('cal-nav-main-links', 'add', 'sm:flex');
                        this.modifyClass('cal-nav-omv', 'remove', 'hidden');
                        this.modifyClass('cal-nav-main-links-alt', 'add', 'hidden');

                        if (this.timerIntervalId) {
                            cancelAnimationFrame(this.timerIntervalId);
                        }
                        if (this.pollingAId) {
                            clearInterval(this.pollingAId);
                        }
                        if (this.pollingBId) {
                            clearInterval(this.pollingBId);
                        }
                        this.evaluateStop(sendData);

                        if (resetRecipe) {
                            this.resetRecipeSelection();
                        }
                    },

                    reset(resetRecipe = true) {
                        this.stop(resetRecipe); // This will also reset the recipe selection if resetRecipe is true
                        this.stepCurrentIndex = 0;
                        if (resetRecipe) {
                            this.recipeSelectedDuration = 0;
                            this.timerRemainingTime = 0;
                            this.stepList = [];
                        } else {
                            this.calculateTotalDuration();
                            this.timerRemainingTime = this.recipeSelectedDuration;
                        }
                        this.batchStartTime = null;
                        this.stepProgressPercentages = this.stepList.map(() => 0);
                        this.stepRemainingTimes = this.stepList.map(step => step.duration);
                        this.overtimeIsActive = false;
                        this.overtimeElapsed = 0;
                    },

                    tick() {
                        if (this.timerIsRunning) {
                            this.timerElapsedSeconds = (new Date() - this.batchStartTime) / 1000;

                            if (this.timerElapsedSeconds < (this.recipeSelectedDuration + 1)) {
                                this.timerRemainingTime = Math.max(0, this.recipeSelectedDuration - Math.floor(this.timerElapsedSeconds));
                                this.updateProgress(this.timerElapsedSeconds);
                                
                                // Check for capture points
                                this.recipeCapturePoints.forEach(point => {

                                    if (Math.abs(this.timerElapsedSeconds - point) < this.recipeCaptureThreshold && !this
                                        .recipeCapturePointsDone.includes(point)) {
                                        console.log(
                                            `Image capture point: ${point}, elapsed time: ${this.timerElapsedSeconds}`
                                            ); // Debug log
                                        this.captureImage(this.getStepCurrentIndex(this.timerElapsedSeconds), point);
                                        this.recipeCapturePointsDone.push(point);
                                    }
                                });

                                this.timerProgressPosition = this.timerElapsedSeconds / this.recipeSelectedDuration;

                            } else {
                                this.timerRemainingTime = 0;
                                this.overtimeIsActive = true;
                                this.overtimeElapsed = Math.floor(this.timerElapsedSeconds - this.recipeSelectedDuration);
                                this.timerProgressPosition = 1;

                                if (this.overtimeElapsed >= this.overtimeMaxDuration) {
                                    this.stop(true, true); // This will reset the recipe selection when the timer completes
                                    return;
                                }
                            }

                            this.timerIntervalId = requestAnimationFrame(() => this.tick());
                        }
                    },

                    updateProgress(elapsedSeconds) {
                        let stepStartTime = 0;
                        for (let i = 0; i < this.stepList.length; i++) {
                            let stepDuration = this.stepList[i].duration;
                            let stepEndTime = stepStartTime + stepDuration;

                            if (elapsedSeconds < stepEndTime) {
                                this.stepCurrentIndex = i;
                                let stepElapsedTime = elapsedSeconds - stepStartTime;
                                this.stepProgressPercentages[i] = Math.min(100, ((stepElapsedTime + 2) / stepDuration) * 100);
                                this.stepRemainingTimes[i] = Math.max(0, stepDuration - Math.ceil(stepElapsedTime));
                                break;
                                // } else {
                                //     console.log('hehe');
                                //     this.stepProgressPercentages[i] = 100;
                                //     this.stepRemainingTimes[i] = 0;
                            }

                            stepStartTime = stepEndTime;
                        }
                    },

                    evaluateStop(sendData = false) {
                        if (this.batchStartTime === null) return;

                        const difference = Math.abs(this.timerElapsedSeconds - this.recipeSelectedDuration);

                        if (difference <= this.evalTolerance) {
                            this.batchEval = 'on_time';
                        } else if (this.timerElapsedSeconds < this.recipeSelectedDuration) {
                            this.batchEval = 'too_soon';
                        } else {
                            this.batchEval = 'too_late';
                        }

                        if (sendData) {
                            // Prepare and send the JSON data
                            const jsonData = {
                                server_url: '{{ route('home') }}',
                                recipe_id: this.recipeSelected.id.toString(),
                                code: this.batchCode,
                                line: this.batchLine,
                                team: this.batchTeam,
                                user_1_emp_id: '{{ Auth::user()->emp_id }}',
                                user_2_emp_id: this.userq,
                                eval: this.batchEval,
                                start_at: this.formatDateTime(this.batchStartTime),
                                end_at: this.formatDateTime(new Date()),
                                images: this.batchImages,
                                amps: this.batchAmps
                            };
                            console.log(jsonData);
                            this.sendData(jsonData);
                        }
                    },

                    formatTime(seconds) {
                        const minutes = Math.floor(seconds / 60);
                        const remainingSeconds = Math.floor(seconds % 60);
                        return `${minutes.toString().padStart(2, '0')}:${remainingSeconds.toString().padStart(2, '0')}`;
                    },

                    formatDateTime(date) {
                        const year = date.getFullYear();
                        const month = String(date.getMonth() + 1).padStart(2, '0');
                        const day = String(date.getDate()).padStart(2, '0');
                        const hours = String(date.getHours()).padStart(2, '0');
                        const minutes = String(date.getMinutes()).padStart(2, '0');
                        const seconds = String(date.getSeconds()).padStart(2, '0');

                        return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
                    },

                    sendData(jsonData) {
                        // Add captured images to JSON payload
                        // jsonData.captured_images = this.batchImages;

                        fetch('http://127.0.0.1:92/send-data', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify(jsonData),
                            })
                            .then(response => {
                                if (!response.ok) {
                                    throw new Error('Network response was not ok');
                                }
                                return response.json();
                            })
                            .then(data => {
                                console.log('Success:', data);
                                notyfSuccess('{{ __('Data terkirim') }}');
                            })
                            .catch((error) => {
                                console.error('Error:', error);
                                notyfError('{{ __('Data gagal terkirim') }}');
                            });
                    },

                    captureImage(stepIndex, capturePoint) {
                        console.log(`Image capture step index: ${stepIndex}, time: ${capturePoint}`);
                        fetch('http://127.0.0.1:92/get-photo')
                            .then(response => response.blob())
                            .then(imageBlob => {
                                return new Promise((resolve, reject) => {
                                    const reader = new FileReader();
                                    reader.onloadend = () => resolve(reader.result);
                                    reader.onerror = reject;
                                    reader.readAsDataURL(imageBlob);
                                });
                            })
                            .then(base64Image => {
                                const elapsedSeconds = this.timerElapsedSeconds;
                                console.log(`Image capture success at: ${elapsedSeconds}`);
                                this.batchImages.push({
                                    step_index: stepIndex,
                                    taken_at: elapsedSeconds,
                                    image: base64Image
                                });
                            })
                            .catch(error => {
                                console.error('Error capturing image:', error);
                            });
                    },

                    getTotalPreviousStepsDuration(index) {
                        return this.stepList.slice(0, index).reduce((sum, step) => sum + Number(step.duration), 0);
                    },

                    getStepCurrentIndex(elapsedSeconds) {
                        let recipeSelectedDuration = 0;
                        for (let i = 0; i < this.stepList.length; i++) {
                            recipeSelectedDuration += Number(this.stepList[i].duration);
                            if (elapsedSeconds < recipeSelectedDuration) {
                                return i;
                            }
                        }
                        return this.stepList.length - 1; // Return last step if elapsed time exceeds total duration
                    },
                };
            }
        </script>
    @endif
</div>
