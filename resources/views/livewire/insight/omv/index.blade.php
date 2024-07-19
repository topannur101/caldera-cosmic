<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] 
class extends Component {};

?>

<x-slot name="title">{{ __('Open Mill Validator') }}</x-slot>

<x-slot name="header">
    <x-nav-insights-omv></x-nav-insights-omv>
</x-slot>

<div id="content" class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200">
    <div x-data="{ 
        ...multiStepTimer(), 
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
        },
        wizard: {
            currentStep: 1,
            mixingType: '',
            nextStep() {
                if (this.currentStep < 2) {
                    this.currentStep++;
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
                    alert('Please select both mixing type and recipe before finishing.');
                }
            }
        }
    }" x-init="loadRecipes()">
    
        <div class="mb-4">
            <p x-text="'Total Time: ' + formatTime(totalDuration)"></p>
            <p x-text="'Current Step: ' + (currentStepIndex + 1)"></p>
            <p x-text="'Total Remaining Time: ' + formatTime(remainingTime)"></p>
            <p x-show="isOvertime" x-text="'Overtime: ' + formatTime(overtimeElapsed)"></p>
            <p x-show="evaluation !== ''" x-text="'Evaluation: ' + evaluation"></p>
            <p x-show="activeRecipe" x-text="'Active Recipe: ' + activeRecipe.name"></p>
        </div>
    
        <x-modal name="recipes">
            <div x-data="wizard" class="p-6">    
                <!-- Step 1: Mixing Type -->
                <div x-show="currentStep === 1">
                    <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                        {{ __('Pilih tipe mixing')}}
                    </h2>
                    <fieldset class="grid gap-2 mt-6">
                        <div>
                            <input type="radio" name="mixingType" id="mixingTypeNew"
                                class="peer hidden [&:checked_+_label_svg]:block" value="new" x-model="mixingType" />
                            <label for="mixingTypeNew"
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
                            <input type="radio" name="mixingType" id="mixingTypeRegrind"
                                class="peer hidden [&:checked_+_label_svg]:block" value="regrind" x-model="mixingType" />
                            <label for="mixingTypeRegrind"
                                class="block h-full cursor-pointer rounded-lg border border-neutral-200 dark:border-neutral-700 p-4 hover:border-neutral-300 dark:hover:border-neutral-700 peer-checked:border-caldy-500 peer-checked:ring-1 peer-checked:ring-caldy-500">
                                <div class="flex items-center justify-between">
                                    <p>{{ __('Regrind') }}</p>
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
                            <label for="mixingTypeScrap"
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
                        <template x-for="recipe in recipes" :key="recipe.id">
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
                    </fieldset>
                </div>
    
                <!-- Navigation buttons -->
                <div class="flex justify-between mt-8">
                    <x-secondary-button x-show="currentStep > 1" @click="prevStep">
                        {{ __('Mundur') }}
                    </x-secondary-button>
                    <x-secondary-button type="button" x-show="currentStep < 2" @click="nextStep">
                        {{ __('Maju') }}
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
    
        <div class="mb-4">
            <x-secondary-button type="button" @click="openWizard()">{{ __('Mulai') }}</x-secondary-button>
            <x-secondary-button type="button" @click="loadPhoto">{{ __('Ambil foto') }}</x-secondary-button>
            <x-secondary-button type="button" @click="loadSerial">{{ __('Data serial') }}</x-secondary-button>
            <x-secondary-button type="button" @click="start()" x-show="!isRunning"><i class="fa fa-fw fa-play me-2"></i>Start</x-secondary-button>
            <x-secondary-button type="button" @click="stop()" x-show="isRunning"><i class="fa fa-fw fa-stop me-2"></i>Stop</x-secondary-button>
            <x-secondary-button type="button" @click="reset()" ><i class="fa fa-fw fa-refresh me-2"></i>Restart</x-secondary-button> 
        </div>
    
        <div class="grid grid-cols-3 gap-x-3 mt-8">
            <template x-for="(step, index) in steps" :key="index">
                <div class="bg-white dark:bg-neutral-800 shadow rounded-lg p-4 mb-4">
                    <div class="mb-3 flex justify-between items-center">
                        <span class="text-xs uppercase" x-text="'{{ __('Langkah') }}' + ' ' + (index + 1)"></span>
                        <span class="text-xs" x-text="formatTime(stepRemainingTimes[index]) + ' / ' + formatTime(step[1])"></span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-1.5 mb-4 dark:bg-gray-700">
                        <div class="bg-caldy-600 h-1.5 rounded-full dark:bg-caldy-500 transition-all duration-200"
                            :style="'width: ' + stepProgresses[index] + '%'"></div>
                    </div>
                    <div>
                        <span class="text-2xl" x-text="step[0]"></span>
                    </div>
                </div>
            </template>
        </div>
    </div>
    
    <script>
    function multiStepTimer() {
        return {
            recipes: [],
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
            maxOvertimeDuration: 30,
            tolerance: 5,
            evaluation: '',
            pollingIntervalId: null,

            async loadRecipes() {
                // Option 1: Load recipes from API
                // try {
                //     const response = await fetch('/api/recipes');
                //     this.recipes = await response.json();
                // } catch (error) {
                //     console.error('Failed to load recipes:', error);
                //     this.recipes = []; // Fallback to empty array if API fails
                // }

                // Option 2: Hardcoded recipes
                this.recipes = [
                    {
                        id: 1,
                        name: 'Simple Recipe',
                        steps: [
                            ['Recipe step 1', 3],
                            ['Recipe step 2', 3],
                            ['Recipe step 3', 3]
                        ]
                    },
                    {
                        id: 2,
                        name: 'Complex Recipe',
                        steps: [
                            ['Prep ingredients', 5],
                            ['Cook base', 5],
                            ['Add spices', 5],
                            ['Simmer', 5],
                            ['Garnish', 5]
                        ]
                    }
                ];
            },

            applySelectedRecipe() {
                const selectedRecipe = this.recipes.find(r => r.id == this.selectedRecipeId);
                if (selectedRecipe) {
                    this.activeRecipe = selectedRecipe;
                    this.steps = selectedRecipe.steps;
                    this.$dispatch('close');
                    this.reset();
                    this.startPolling(); // Start polling when a recipe is selected
                }
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
                            if (data.data > 0 && !this.isRunning) {
                                this.start();
                                clearInterval(this.pollingIntervalId);
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching data:', error);
                        });
                }, 3000); // Poll every 1 second
            },

            openWizard() {
                if (this.isRunning) {
                    alert('Please stop the current timer before selecting a new recipe.');
                    return;
                }
                this.$dispatch('open-modal', 'recipes')
            },

            start() {
                if (!this.isRunning && this.steps.length > 0) {
                    this.isRunning = true;
                    this.startTime = Date.now();
                    this.remainingTime = this.totalDuration;
                    this.tick();
                    
                    // Additional safeguard to ensure polling stops
                    if (this.pollingIntervalId) {
                        clearInterval(this.pollingIntervalId);
                        this.pollingIntervalId = null;
                    }
                } else if (this.steps.length === 0) {
                    alert('Please select a recipe before starting the timer.');
                }
            },

            stop() {
                this.isRunning = false;
                if (this.intervalId) {
                    cancelAnimationFrame(this.intervalId);
                }
                if (this.pollingIntervalId) {
                    clearInterval(this.pollingIntervalId);
                }
                this.evaluateStop();
            },

            reset() {
                this.stop();
                this.currentStepIndex = 0;
                this.totalDuration = this.steps.reduce((sum, step) => sum + step[1], 0);
                this.remainingTime = this.totalDuration;
                this.startTime = null;
                this.stepProgresses = this.steps.map(() => 0);
                this.stepRemainingTimes = this.steps.map(step => step[1]);
                this.isOvertime = false;
                this.overtimeElapsed = 0;
                this.evaluation = '';
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
                            this.stop();
                            return;
                        }
                    }

                    this.intervalId = requestAnimationFrame(() => this.tick());
                }
            },

            updateProgress(elapsedSeconds) {
                let stepStartTime = 0;
                for (let i = 0; i < this.steps.length; i++) {
                    let stepDuration = this.steps[i][1];
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

            evaluateStop() {
                if (this.startTime === null) return;

                const elapsedTime = (Date.now() - this.startTime) / 1000;
                const difference = Math.abs(elapsedTime - this.totalDuration);

                if (difference <= this.tolerance) {
                    this.evaluation = 'OK';
                } else if (elapsedTime < this.totalDuration) {
                    this.evaluation = 'Too soon';
                } else {
                    this.evaluation = 'Too late';
                }
            },

            formatTime(seconds) {
                const minutes = Math.floor(seconds / 60);
                const remainingSeconds = Math.floor(seconds % 60);
                return `${minutes.toString().padStart(2, '0')}:${remainingSeconds.toString().padStart(2, '0')}`;
            }
        };
    }

    </script>
</div>
