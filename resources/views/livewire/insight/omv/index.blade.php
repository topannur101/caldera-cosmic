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

<div id="content" class="pt-12 pb-3 max-w-7xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
    @if (!Auth::user())
        <div class="flex flex-col items-center gap-y-6 py-20">
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
        <div x-data="{
            ...app(),
            userq: @entangle('userq').live
        }" x-init="loadRecipes();
        fetchLine()">
            <div :class="!isRunning && activeRecipe ? 'cal-glowing z-10' : (isRunning ? 'cal-glow z-10' : '')"
                :style="'--cal-glow-pos: -' + (glowPosition * 100) + '%'">
                <div class="bg-white dark:bg-neutral-800 bg-opacity-80 dark:bg-opacity-80 shadow rounded-lg flex w-full items-stretch">
                    <div class="flex justify-between grow mx-6 my-4">
                        <div class="flex flex-col justify-center">
                            <div class="text-2xl" x-text="activeRecipe ? activeRecipe.name : '{{ __('Menunggu...') }}'">
                            </div>
                            <div class="flex gap-x-3 text-neutral-500">
                                <div><span>{{ __('Tipe') }}</span><span
                                        @click="start()">{{ ': ' }}</span><span
                                        x-text="activeRecipe ? mixingType.toUpperCase() : '{{ __('Tak ada resep aktif') }}'"></span>
                                </div>
                                <div>|</div>
                                <div @click="start()">
                                    <span>{{ __('Evaluasi terakhir') }}</span><span>{{ ': ' }}</span><span
                                        x-text="evaluation == 'on_time' ? '{{ __('Tepat waktu') }}' : ( evaluation == 'too_soon' ? '{{ __('Terlalu awal') }}' : ( evaluation == 'too_late' ? '{{ __('Terlambat') }}' : '{{ __('Tak ada') }}' ))"></span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center">
                            <div class="text-2xl font-mono" x-text="formatTime(remainingTime)" x-show="isRunning"
                                :class="remainingTime == 0 ? 'text-red-500' : ''"></div>
                        </div>
                    </div>
                    <div class="flex">
                        <div class="px-2 py-4"
                            :class="!team && isRunning ? 'bg-red-200 dark:bg-red-900 dark:text-white fa-fade' : ''">
                            <label for="team"
                                class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Tim') }}</label>
                            <x-select id="team" x-model="team">
                                <option value=""></option>
                                <option value="A">A</option>
                                <option value="B">B</option>
                                <option value="C">C</option>
                            </x-select>
                        </div>
                        <div class="px-2 py-4 w-48"
                            :class="!userq && isRunning ? 'bg-red-200 dark:bg-red-900 dark:text-white fa-fade' : ''"
                            wire:key="user-select" x-data="{ open: false }"
                            x-on:user-selected="userq = $event.detail; open = false">
                            <div x-on:click.away="open = false">
                                <label for="inv-user"
                                    class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Mitra kerja') }}</label>
                                <x-text-input-icon x-model="userq" icon="fa fa-fw fa-user" x-on:change="open = true"
                                    x-ref="userq" x-on:focus="open = true" id="inv-user" type="text"
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
                        x-show="!isRunning && !activeRecipe"><i
                            class="fa fa-play mr-2"></i>{{ __('Mulai') }}</x-primary-button>
                    <x-primary-button class="m-4" type="button" size="lg" @click="resetRecipeSelection()"
                        x-show="!isRunning && activeRecipe"><i
                            class="fa fa-undo mr-2"></i>{{ __('Ulangi') }}</x-primary-button>
                    <x-primary-button class="m-4" type="button" size="lg" @click="stop(true, true)" x-cloak
                        x-show="isRunning"><i class="fa fa-stop mr-2"></i>{{ __('Stop') }}</x-primary-button>
                </div>
            </div>

            <x-modal name="recipes" focusable>
                <div class="p-6">
                    <!-- Step 1: Batch identity -->
                    <div x-show="currentStep === 1">
                        <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                            {{ __('Identitas gilingan') }}
                        </h2>
                        <div class="mt-6">
                            <label for="code"
                                class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Kode') }}</label>
                            <x-text-input id="code" x-model="code" type="text" />
                        </div>
                    </div>
                    <!-- Step 2: Mixing Type -->
                    <div x-show="currentStep === 2">
                        <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                            {{ __('Pilih tipe mixing') }}
                        </h2>
                        <fieldset class="grid gap-2 mt-6">
                            <div>
                                <input type="radio" name="mixingType" id="mixingTypeNew"
                                    class="peer hidden [&:checked_+_label_svg]:block" value="new"
                                    x-model="mixingType" />
                                <label for="mixingTypeNew" @click="setTimeout(() => { nextStep() }, 200);"
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
                                <input type="radio" name="mixingType" id="mixingTypeRemixing"
                                    class="peer hidden [&:checked_+_label_svg]:block" value="remixing"
                                    x-model="mixingType" />
                                <label for="mixingTypeRemixing" @click="setTimeout(() => { nextStep() }, 200);"
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
                                <input type="radio" name="mixingType" id="mixingTypeScrap"
                                    class="peer hidden [&:checked_+_label_svg]:block" value="scrap"
                                    x-model="mixingType" />
                                <label for="mixingTypeScrap" @click="setTimeout(() => { nextStep() }, 200);"
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
                    <div x-show="currentStep === 3">
                        <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                            {{ __('Pilih resep') }}
                        </h2>
                        <fieldset class="grid gap-2 mt-6 max-h-96 overflow-y-scroll p-1">
                            <template x-if="filteredRecipes.length > 0">
                                <template x-for="recipe in filteredRecipes" :key="recipe.id">
                                    <div>
                                        <input type="radio" name="recipe" :id="'recipe-' + recipe.id"
                                            class="peer hidden [&:checked_+_label_svg]:block" :value="recipe.id"
                                            x-model="selectedRecipeId" />
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
                            <template x-if="filteredRecipes.length === 0">
                                <div class="text-center text-neutral-500">
                                    {{ __('Tidak ada resep untuk tipe ini') }}
                                </div>
                            </template>
                        </fieldset>
                    </div>

                    <!-- Navigation buttons -->
                    <div class="flex mt-8 justify-end gap-x-3">
                        <x-secondary-button type="button" x-show="currentStep > 1" @click="prevStep">
                            {{ __('Mundur') }}
                        </x-secondary-button>
                        <x-secondary-button type="button" x-show="currentStep < 3" @click="nextStep">
                            {{ __('Maju') }}
                        </x-secondary-button>
                        <x-primary-button type="button" x-show="currentStep === 3" @click="finishWizard">
                            {{ __('Terapkan') }}
                        </x-primary-button>
                    </div>
                </div>
            </x-modal>

            <div x-show="!activeRecipe">
                <div class="py-20">
                    <div class="text-center text-neutral-500">
                        <div class="text-2xl mb-2">{{ __('Hai,') . ' ' . Auth::user()->name . '!' }}</div>
                        <div class="text-sm">{{ __('Jangan lupa pilih tim dan mitra kerjamu') }}</div>
                    </div>
                </div>
            </div>

            <div x-show="activeRecipe" class="grid grid-cols-2 gap-3 mt-10">
                <template x-for="(step, index) in steps" :key="index">
                    <div class="bg-white dark:bg-neutral-800 shadow rounded-lg p-4"
                        :class="currentStepIndex == index && isRunning ? 'cal-shimmer' : ''">
                        <div class="flex gap-4  w-full mb-6">
                            <div class="w-12 h-12 rounded-full flex items-center justify-center"
                                :class="(currentStepIndex > index && isRunning) ? 'bg-green-500 text-neutral-800' : ((
                                        currentStepIndex == index && isRunning) ? 'bg-yellow-500 text-neutral-800' :
                                    'bg-neutral-800 dark:bg-neutral-200 text-white dark:text-neutral-800')">
                                <span class="text-2xl font-bold" x-text="index + 1"></span>
                            </div>
                            <div class="grow">
                                <div class="flex justify-between items-center mb-2">
                                    <div class="flex gap-x-3"
                                        :class="currentStepIndex == index && isRunning ? 'fa-fade' : ''">
                                        <i x-show="currentStepIndex == index && isRunning"
                                            class="fa-solid fa-spinner fa-spin-pulse"></i>
                                        <span class="text-xs uppercase"
                                            x-text="(currentStepIndex > index && isRunning) ? '{{ __('Selesai') }}' : ((currentStepIndex == index && isRunning) ? '{{ __('Berjalan') }}' : '{{ __('Menunggu') }}')"></span>
                                    </div>
                                    <span class="text-xs font-mono"
                                        x-text="formatTime(stepRemainingTimes[index])"></span>
                                </div>
                                <div class="relative w-full bg-gray-200 rounded-full h-1.5 dark:bg-gray-700">
                                    <div class="bg-caldy-600 h-1.5 rounded-full dark:bg-caldy-500 transition-all duration-200"
                                        :style="'width: ' + stepProgresses[index] + '%'"></div>
                                    <!-- Capture points -->
                                    <template
                                        x-for="point in capturePoints.filter(p => p >= getTotalPreviousStepsDuration(index) && p < getTotalPreviousStepsDuration(index + 1))"
                                        :key="point">
                                        <div class="absolute w-2 h-2 bg-caldy-500 rounded-full top-4 transform -translate-y-1/2"
                                            :style="'left: ' + ((point - getTotalPreviousStepsDuration(index)) / step.duration *
                                                100) + '%'"
                                            :class="elapsedSeconds >= point ? 'opacity-30' : ''"></div>
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

        <script>
            function app() {
                return {
                    recipes: [],
                    filteredRecipes: [],
                    selectedRecipeId: null,
                    activeRecipe: null,
                    steps: [],
                    totalDuration: 0,
                    currentStepIndex: 0,
                    remainingTime: 0,
                    isRunning: false,
                    intervalId: null,
                    startTime: null,
                    stepProgresses: [],
                    stepRemainingTimes: [],
                    isOvertime: false,
                    overtimeElapsed: 0,
                    maxOvertimeDuration: 900,
                    tolerance: 120, // 60 60 
                    evaluation: '',
                    pollingIntervalId: null,
                    team: '',
                    currentStep: 1,
                    mixingType: '',
                    capturePoints: [],
                    capturedImages: [],
                    processedCapturePoints: [],
                    captureThreshold: 1,
                    capturedImages: [],
                    glowPosition: 0,
                    line: '',
                    code: '',

                    async fetchLine() {
                        try {
                            const response = await fetch('http://127.0.0.1:92/get-line');
                            if (!response.ok) {
                                throw new Error('Failed to get line');
                            }
                            this.line = await response.text();
                            console.log('Line fetched:', this.line);
                        } catch (error) {
                            console.error('Failed to fetch line:', error);
                        }
                    },

                    async loadRecipes() {
                        try {
                            const response = await fetch('/api/omv-recipes');
                            this.recipes = await response.json();
                        } catch (error) {
                            console.error('Failed to load recipes:', error);
                            this.recipes = [];
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
                        this.selectedRecipeId = null;
                        this.activeRecipe = null;
                        this.steps = [];
                        this.mixingType = '';
                        this.filteredRecipes = [];
                    },

                    applySelectedRecipe() {
                        const selectedRecipe = this.recipes.find(r => r.id == this.selectedRecipeId);
                        if (selectedRecipe) {
                            this.activeRecipe = selectedRecipe;
                            this.steps = selectedRecipe.steps;
                            this.capturePoints = selectedRecipe.capture_points || [];
                            this.calculateTotalDuration();
                            this.reset(false);
                            this.$dispatch('close');
                            this.startPolling();
                        }
                    },

                    calculateTotalDuration() {
                        this.totalDuration = this.steps.reduce((sum, step) => {
                            const duration = Number(step.duration);
                            return sum + (isNaN(duration) ? 0 : duration);
                        }, 0);

                        // Subtract 1 from the total duration, ensuring it doesn't go below 0
                        this.totalDuration = Math.max(0, this.totalDuration - 1);
                    },

                    startPolling() {
                        if (this.pollingIntervalId) {
                            clearInterval(this.pollingIntervalId);
                        }

                        this.pollingIntervalId = setInterval(() => {
                            fetch('http://127.0.0.1:92/get-data')
                                .then(response => response.json())
                                .then(data => {
                                    console.log('Received data:', data);
                                    if (data.error) {
                                        console.error('Error from server:', data.error);
                                    } else if (data.eval && !this.isRunning) {
                                        this.start();
                                        clearInterval(this.pollingIntervalId);
                                    }
                                })
                                .catch(error => {
                                    console.error('Error fetching data:', error);
                                });
                        }, 3000);
                    },

                    nextStep() {
                        if (this.currentStep < 3) {
                            this.currentStep++;
                            if (this.currentStep === 3) {
                                this.filterRecipes();
                            }
                        }
                    },

                    prevStep() {
                        if (this.currentStep > 1) {
                            this.currentStep--;
                        }
                    },

                    finishWizard() {
                        if (this.mixingType && this.selectedRecipeId) {
                            this.applySelectedRecipe();
                        } else {
                            notyfError('{{ __('Tipe mixing dan resep wajib dipilih') }}');
                        }
                    },

                    openWizard() {
                        if (this.isRunning) {
                            notyfError('{{ __('Hentikan timer sebelum memilih resep baru.') }}');
                            return;
                        }
                        this.currentStep = 1;
                        this.mixingType = '';
                        this.selectedRecipeId = null;
                        this.$dispatch('open-modal', 'recipes')
                    },

                    // Add this new method to filter recipes based on type
                    filterRecipes() {
                        this.filteredRecipes = this.recipes.filter(recipe => recipe.type === this.mixingType);
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
                        if (!this.isRunning && this.steps.length > 0) {
                            this.isRunning = true;
                            this.startTime = new Date(); // Change this to store the full date object
                            this.remainingTime = this.totalDuration;
                            this.processedCapturePoints = []; // Reset processed capture points
                            this.capturedImages = []; // Reset captured images
                            this.tick();

                            if (this.pollingIntervalId) {
                                clearInterval(this.pollingIntervalId);
                                this.pollingIntervalId = null;
                            }

                            this.modifyClass('cal-nav-main-links', 'remove', 'sm:flex');
                            this.modifyClass('cal-nav-omv', 'add', 'hidden');
                            this.modifyClass('cal-nav-main-links-alt', 'remove', 'hidden');

                        } else if (this.steps.length === 0) {
                            notyfError('{{ __('Pilih resep terlebih dahulu sebelum menjalankan timer.') }}');
                        }
                    },

                    stop(resetRecipe = true, sendData = false) {
                        this.isRunning = false;
                        this.modifyClass('cal-nav-main-links', 'add', 'sm:flex');
                        this.modifyClass('cal-nav-omv', 'remove', 'hidden');
                        this.modifyClass('cal-nav-main-links-alt', 'add', 'hidden');

                        if (this.intervalId) {
                            cancelAnimationFrame(this.intervalId);
                        }
                        if (this.pollingIntervalId) {
                            clearInterval(this.pollingIntervalId);
                        }
                        this.evaluateStop(sendData);

                        if (resetRecipe) {
                            this.resetRecipeSelection();
                        }
                    },

                    reset(resetRecipe = true) {
                        this.stop(resetRecipe); // This will also reset the recipe selection if resetRecipe is true
                        this.currentStepIndex = 0;
                        if (resetRecipe) {
                            this.totalDuration = 0;
                            this.remainingTime = 0;
                            this.steps = [];
                        } else {
                            this.calculateTotalDuration();
                            this.remainingTime = this.totalDuration;
                        }
                        this.startTime = null;
                        this.stepProgresses = this.steps.map(() => 0);
                        this.stepRemainingTimes = this.steps.map(step => step.duration);
                        this.isOvertime = false;
                        this.overtimeElapsed = 0;
                    },

                    tick() {
                        if (this.isRunning) {
                            const now = Date.now();
                            const elapsedSeconds = (now - this.startTime) / 1000;

                            if (elapsedSeconds < (this.totalDuration + 1)) {
                                this.remainingTime = Math.max(0, this.totalDuration - Math.floor(elapsedSeconds));
                                this.updateProgress(elapsedSeconds);

                                // Check for capture points
                                this.capturePoints.forEach(point => {
                                    if (Math.abs(elapsedSeconds - point) < this.captureThreshold && !this
                                        .processedCapturePoints.includes(point)) {
                                        console.log(
                                            `Capturing image at point: ${point}, elapsed time: ${elapsedSeconds}`
                                            ); // Debug log
                                        this.captureImage(this.getCurrentStepIndex(elapsedSeconds), point);
                                        this.processedCapturePoints.push(point);
                                    }
                                });

                                this.glowPosition = elapsedSeconds / this.totalDuration;

                            } else {
                                this.remainingTime = 0;
                                this.isOvertime = true;
                                this.overtimeElapsed = Math.floor(elapsedSeconds - this.totalDuration);
                                this.glowPosition = 1;

                                if (this.overtimeElapsed >= this.maxOvertimeDuration) {
                                    this.stop(true, true); // This will reset the recipe selection when the timer completes
                                    return;
                                }
                            }

                            this.intervalId = requestAnimationFrame(() => this.tick());
                        }
                    },

                    updateProgress(elapsedSeconds) {
                        let stepStartTime = 0;
                        for (let i = 0; i < this.steps.length; i++) {
                            let stepDuration = this.steps[i].duration;
                            let stepEndTime = stepStartTime + stepDuration;

                            if (elapsedSeconds < stepEndTime) {
                                this.currentStepIndex = i;
                                let stepElapsedTime = elapsedSeconds - stepStartTime;
                                this.stepProgresses[i] = Math.min(100, ((stepElapsedTime + 2) / stepDuration) * 100);
                                this.stepRemainingTimes[i] = Math.max(0, stepDuration - Math.ceil(stepElapsedTime));
                                break;
                                // } else {
                                //     console.log('hehe');
                                //     this.stepProgresses[i] = 100;
                                //     this.stepRemainingTimes[i] = 0;
                            }

                            stepStartTime = stepEndTime;
                        }
                    },

                    evaluateStop(sendData = false) {
                        if (this.startTime === null) return;

                        const endTime = new Date();
                        const elapsedTime = (endTime - this.startTime) / 1000;
                        const difference = Math.abs(elapsedTime - this.totalDuration);

                        if (difference <= this.tolerance) {
                            this.evaluation = 'on_time';
                        } else if (elapsedTime < this.totalDuration) {
                            this.evaluation = 'too_soon';
                        } else {
                            this.evaluation = 'too_late';
                        }

                        if (sendData) {
                            // Prepare and send the JSON data
                            const jsonData = {
                                server_url: '{{ route('home') }}',
                                recipe_id: this.activeRecipe.id.toString(),
                                line: this.line,
                                team: this.team,
                                user_1_emp_id: '{{ Auth::user()->emp_id }}',
                                user_2_emp_id: this.userq,
                                eval: this.evaluation,
                                start_at: this.formatDateTime(this.startTime),
                                end_at: this.formatDateTime(endTime),
                                captured_images: this.capturedImages
                            };
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
                        jsonData.captured_images = this.capturedImages;

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

                    captureImage(stepIndex, captureTime) {
                        console.log(`Attempt to capture image at step ${stepIndex}, time ${captureTime}`);
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
                                console.log(`Image captured successfully at time ${captureTime}`);
                                this.capturedImages.push({
                                    stepIndex: stepIndex,
                                    captureTime: captureTime,
                                    image: base64Image,
                                    timestamp: new Date().toISOString()
                                });
                            })
                            .catch(error => {
                                console.error('Error capturing image:', error);
                            });
                    },

                    getTotalPreviousStepsDuration(index) {
                        return this.steps.slice(0, index).reduce((sum, step) => sum + Number(step.duration), 0);
                    },

                    get elapsedSeconds() {
                        return this.isRunning ? (Date.now() - this.startTime) / 1000 : 0;
                    },

                    getCurrentStepIndex(elapsedSeconds) {
                        let totalDuration = 0;
                        for (let i = 0; i < this.steps.length; i++) {
                            totalDuration += Number(this.steps[i].duration);
                            if (elapsedSeconds < totalDuration) {
                                return i;
                            }
                        }
                        return this.steps.length - 1; // Return last step if elapsed time exceeds total duration
                    },
                };
            }
        </script>
    @endif
</div>
