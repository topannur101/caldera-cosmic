<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] 
class extends Component {
    
    public string $userq = '';
    public int    $user_id = 0;

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

<div id="content" class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
    @if(!Auth::user())
        <div class="py-20">
            <div class="text-center text-neutral-300 dark:text-neutral-700 text-5xl mb-3">
                <i class="fa fa-exclamation-circle"></i>
            </div>
            <div class="text-center text-neutral-500 dark:text-neutral-600">{{ __('Masuk untuk menggunakan fitur ini') }}
            </div>
        </div>        
    @else
        <div x-data="{ 
            ...app(),
            userq: @entangle('userq').live,
            photoSrc: '', 
            loadPhoto() { 
                this.photoSrc = ''; 
                this.$nextTick(() => { 
                    this.photoSrc = 'http://localhost:92/get-photo'; 
                    $dispatch('open-modal', 'photo') 
                }); 
            }, 
            serialSrc: '', 
            loadSerial() { 
                this.serialSrc = ''; 
                this.$nextTick(() => { 
                    this.serialSrc = 'http://localhost:92/get-data'; 
                    $dispatch('open-modal', 'serial') 
                }); 
            }
        }" x-init="loadRecipes()">
            <div class="bg-white dark:bg-neutral-800 shadow rounded-lg p-4 flex items-stretch gap-x-6 w-100">
                <div class="flex justify-between grow mx-3">
                    <div class="flex flex-col justify-center">
                        <div class="text-2xl" x-text="activeRecipe ? activeRecipe.name : '{{ __('Menunggu...') }}'"></div>
                        <div class="flex gap-x-3 text-neutral-500">
                            <div><span>{{ __('Tipe')}}</span><span @click="start()">{{ ': '}}</span><span x-text="activeRecipe ? mixingType.toUpperCase() : '{{ __('Tak ada resep aktif') }}'"></span></div>
                            <div>|</div>
                            <div><span>{{ __('Evaluasi terakhir')}}</span><span @click="start()">{{ ': '}}</span><span x-text="evaluation ? evaluation : '{{ __('Tak ada') }}'"></span></div>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <div class="text-2xl font-mono" x-text="formatTime(remainingTime)" x-show="isRunning" :class="remainingTime == 0 ? 'text-red-500' : ''"></div>
                    </div>
                </div>
                <div class="flex gap-x-3">
                    <div>
                        <label for="shift"
                        class="block px-3 mb-2 uppercase text-xs text-neutral-500">{{ __('Shift') }}</label>
                        <x-select id="shift" x-model="shift">
                            <option value=""></option>
                            <option value="1">1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                        </x-select>
                    </div>
                    <div wire:key="user-select" x-data="{ open: false }" x-on:user-selected="userq = $event.detail; open = false" class="w-48">
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
                <x-primary-button type="button" size="lg" @click="openWizard()" x-show="!isRunning && !activeRecipe"><i class="fa fa-play mr-2"></i>{{ __('Mulai') }}</x-primary-button>
                <x-primary-button type="button" size="lg" @click="resetRecipeSelection()" x-show="!isRunning && activeRecipe"><i class="fa fa-undo mr-2"></i>{{ __('Ulangi') }}</x-primary-button>
                <x-primary-button type="button" size="lg" @click="stop(true, true)" x-cloak x-show="isRunning"><i class="fa fa-stop mr-2"></i>{{ __('Stop') }}</x-primary-button>
            </div>
        
            <x-modal name="recipes">
                <div class="p-6">    
                    <!-- Step 1: Mixing Type -->
                    <div x-show="currentStep === 1">
                        <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                            {{ __('Pilih tipe mixing')}}
                        </h2>
                        <fieldset class="grid gap-2 mt-6">
                            <div>
                                <input type="radio" name="mixingType" id="mixingTypeNew"
                                    class="peer hidden [&:checked_+_label_svg]:block" value="new" x-model="mixingType" />
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
                                    class="peer hidden [&:checked_+_label_svg]:block" value="remixing" x-model="mixingType" />
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
                                    class="peer hidden [&:checked_+_label_svg]:block" value="scrap" x-model="mixingType" />
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
        
                    <!-- Step 2: Recipe Selection -->
                    <div x-show="currentStep === 2">
                        <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                            {{ __('Pilih resep')}}
                        </h2>
                        <fieldset class="grid gap-2 mt-6">
                            <template x-if="filteredRecipes.length > 0">
                                <template x-for="recipe in filteredRecipes" :key="recipe.id">
                                    <div>
                                        <input type="radio" name="recipe" :id="'recipe-' + recipe.id"
                                            class="peer hidden [&:checked_+_label_svg]:block" :value="recipe.id" x-model="selectedRecipeId" />
                                        <label :for="'recipe-' + recipe.id"
                                            class="block h-full cursor-pointer rounded-lg border border-neutral-200 dark:border-neutral-700 p-4 hover:border-neutral-300 dark:hover:border-neutral-700 peer-checked:border-caldy-500 peer-checked:ring-1 peer-checked:ring-caldy-500">
                                            <div class="flex items-center justify-between">
                                                <p x-text="recipe.name"></p>
                                                <svg class="hidden h-6 w-6 text-caldy-600" xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20" fill="currentColor">
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
                        <x-primary-button type="button" x-show="currentStep === 2" @click="finishWizard">
                            {{ __('Terapkan') }}
                        </x-primary-button>
                    </div>
                </div>
            </x-modal>
        
            <x-modal name="photo">
                <div class="flex">
                    <iframe class="m-auto" :src="photoSrc" width="320" height="240" frameborder="0"></iframe>
                </div>
            </x-modal>
        
            <x-modal name="serial">
                <div class="flex">
                    <iframe class="m-auto" :src="serialSrc" width="320" height="240" frameborder="0"></iframe>
                </div>
            </x-modal>

            <div x-show="!activeRecipe">
                <div class="py-20">
                    <div class="text-center text-neutral-500">
                        <div class="text-2xl mb-2">{{ __('Hai,') . ' ' . Auth::user()->name . '!' }}</div>
                        <div class="text-sm">{{ __('Jangan lupa pilih shift dan mitra kerjamu') }}</div>
                    </div>
                </div>
            </div>    
            <div x-show="activeRecipe" class="grid grid-cols-3 gap-x-3 mt-3">
                <template x-for="(step, index) in steps" :key="index">
                    <div class="bg-white dark:bg-neutral-800 shadow rounded-lg p-4 mb-4" :class="currentStepIndex != index && isRunning ? 'opacity-30': ''">
                        <div class="mb-3 flex justify-between items-center">
                            <div class="flex gap-x-3" :class="currentStepIndex == index && isRunning ? 'fa-fade': ''">
                                <i x-show="currentStepIndex == index && isRunning" class="fa-solid fa-spinner fa-spin-pulse"></i>
                                <span class="text-xs uppercase"  x-text="'{{ __('Langkah') }}' + ' ' + (index + 1)"></span>
                            </div>
                            <span class="text-xs font-mono" x-text="formatTime(stepRemainingTimes[index])"></span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-1.5 mb-4 dark:bg-gray-700">
                            <div class="bg-caldy-600 h-1.5 rounded-full dark:bg-caldy-500 transition-all duration-200"
                                :style="'width: ' + stepProgresses[index] + '%'"></div>
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
                tolerance: 240,
                evaluation: '',
                pollingIntervalId: null,
                shift: '',
                currentStep: 1,
                mixingType: '',
                
                async loadRecipes() {
                    try {
                        const response = await fetch('/api/omv-recipes');
                        this.recipes = await response.json();
                    } catch (error) {
                        console.error('Failed to load recipes:', error);
                        this.recipes = []; // Fallback to empty array if API fails
                    }
                    //     {
                    //         "id": 1,
                    //         "type": "new", // new property
                    //         "name": "Simple Recipe",

                    //         "steps": [
                    //         {
                    //             "description": "Test",
                    //             "duration": "50"
                    //         },
                    //         {
                    //             "description": "st",
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
                },

                startPolling() {
                    if (this.pollingIntervalId) {
                        clearInterval(this.pollingIntervalId);
                    }
                    
                    this.pollingIntervalId = setInterval(() => {
                        fetch('http://localhost:92/get-data')
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
                    if (this.currentStep < 2) {
                        this.currentStep++;
                        if (this.currentStep === 2) {
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
                        alert('{{ __('Tipe mixing dan resep wajib dipilih') }}');
                    }
                },
                openWizard() {
                    if (this.isRunning) {
                        alert('{{ __("Hentikan timer sebelum memilih resep baru.") }}');
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

                start() {
                    if (!this.isRunning && this.steps.length > 0) {
                        this.isRunning = true;
                        this.startTime = new Date(); // Change this to store the full date object
                        this.remainingTime = this.totalDuration;
                        this.tick();
                        
                        if (this.pollingIntervalId) {
                            clearInterval(this.pollingIntervalId);
                            this.pollingIntervalId = null;
                        }
                    } else if (this.steps.length === 0) {
                        alert('{{ __("Pilih resep terlebih dahulu sebelum menjalankan timer.") }}');
                    }
                },

                stop(resetRecipe = true, sendData = false) {
                    this.isRunning = false;
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

                        if (elapsedSeconds <= this.totalDuration) {
                            this.remainingTime = Math.max(0, this.totalDuration - Math.floor(elapsedSeconds));
                            this.updateProgress(elapsedSeconds);
                        } else {
                            this.remainingTime = 0;
                            this.isOvertime = true;
                            this.overtimeElapsed = Math.floor(elapsedSeconds - this.totalDuration);

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
                            this.stepProgresses[i] = (stepElapsedTime / stepDuration) * 100;
                            this.stepRemainingTimes[i] = Math.max(0, stepDuration - Math.ceil(stepElapsedTime));
                            break;
                        } else {
                            this.stepProgresses[i] = 100;
                            this.stepRemainingTimes[i] = 0;
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
                            user_1_emp_id: '{{ Auth::user()->emp_id }}',
                            user_2_emp_id: this.userq,
                            eval: this.evaluation,
                            start_at: this.formatDateTime(this.startTime),
                            end_at: this.formatDateTime(endTime),
                            shift: this.shift
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
                    fetch('http://localhost:92/send-data', {
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
                        notyfSuccess('{{ __("Data terkirim") }}');
                    })
                    .catch((error) => {
                        console.error('Error:', error);
                        notyfError('{{ __("Data gagal terkirim") }}');
                    });
                }
            };
        }

        </script>    
    @endif
</div>
