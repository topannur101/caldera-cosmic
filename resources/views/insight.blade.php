<x-app-layout>
   <x-slot name="header">
      <header class="bg-white dark:bg-neutral-800 shadow">
         <div class="flex justify-between max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
             <div>
               <h2 class="font-semibold text-xl text-neutral-800 dark:text-neutral-200 leading-tight">
                  <div class="inline-block py-6">{{ __('Wawasan') }}</div>
               </h2>
             </div>                    
         </div> 
     </header>

  </x-slot>

    <div id="content" class="py-12 max-w-xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200 grid gap-1">
        <x-card-link href="{{ route('insight', ['nav' => 'acm']) }}">
            <div class="flex">
                <div>
                    <div class="relative flex w-32 h-full bg-neutral-200 dark:bg-neutral-700">
                        <div class="m-auto">
                            <svg xmlns="http://www.w3.org/2000/svg"
                                class="block h-16 w-auto text-neutral-800 dark:text-neutral-200 opacity-25"
                                viewBox="0 0 24.00 24.00" fill="none">
                                <g id="SVGRepo_bgCarrier" stroke-width="0" />
                                <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round" />
                                <g id="SVGRepo_iconCarrier">
                                    <path
                                        d="M3 13H8.5L10 15L12 9L14 13H17M6.2 19H17.8C18.9201 19 19.4802 19 19.908 18.782C20.2843 18.5903 20.5903 18.2843 20.782 17.908C21 17.4802 21 16.9201 21 15.8V8.2C21 7.0799 21 6.51984 20.782 6.09202C20.5903 5.71569 20.2843 5.40973 19.908 5.21799C19.4802 5 18.9201 5 17.8 5H6.2C5.0799 5 4.51984 5 4.09202 5.21799C3.71569 5.40973 3.40973 5.71569 3.21799 6.09202C3 6.51984 3 7.07989 3 8.2V15.8C3 16.9201 3 17.4802 3.21799 17.908C3.40973 18.2843 3.71569 18.5903 4.09202 18.782C4.51984 19 5.07989 19 6.2 19Z"
                                        stroke="#000000" stroke-width="0.8399999999999999" stroke-linecap="round"
                                        stroke-linejoin="round" />
                                </g>
                            </svg>
                        </div>
                        <img class="absolute opacity-70 hover:opacity-100 transition-opacity w-full h-full object-cover dark:brightness-75 top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2"
                            src="/ins-acm.jpg" />
                    </div>
                </div>
                <div class="flex grow py-4 px-2 sm:p-6">
                    <div class="grow">
                        <div class=" text-lg font-medium text-neutral-900 dark:text-neutral-100">
                            {{ __('Assembly conveyor monitoring') }}
                        </div>
                        <div class="text-sm text-neutral-600 dark:text-neutral-400">
                            {{ __('Sistem monitoring kecepatan konveyor yang terletak di proses assembly.') }}
                        </div>
                    </div>
                </div>
            </div>
        </x-card-link>
        <x-card-link href="{{ route('insight', ['nav' => 'rtm']) }}">
            <div class="flex">
                <div>
                    <div class="relative flex w-32 h-full bg-neutral-200 dark:bg-neutral-700">
                        <div class="m-auto">
                            <svg xmlns="http://www.w3.org/2000/svg"
                                class="block h-16 w-auto text-neutral-800 dark:text-neutral-200 opacity-25"
                                viewBox="0 0 24.00 24.00" fill="none">
                                <g id="SVGRepo_bgCarrier" stroke-width="0" />
                                <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round" />
                                <g id="SVGRepo_iconCarrier">
                                    <path
                                        d="M3 13H8.5L10 15L12 9L14 13H17M6.2 19H17.8C18.9201 19 19.4802 19 19.908 18.782C20.2843 18.5903 20.5903 18.2843 20.782 17.908C21 17.4802 21 16.9201 21 15.8V8.2C21 7.0799 21 6.51984 20.782 6.09202C20.5903 5.71569 20.2843 5.40973 19.908 5.21799C19.4802 5 18.9201 5 17.8 5H6.2C5.0799 5 4.51984 5 4.09202 5.21799C3.71569 5.40973 3.40973 5.71569 3.21799 6.09202C3 6.51984 3 7.07989 3 8.2V15.8C3 16.9201 3 17.4802 3.21799 17.908C3.40973 18.2843 3.71569 18.5903 4.09202 18.782C4.51984 19 5.07989 19 6.2 19Z"
                                        stroke="#000000" stroke-width="0.8399999999999999" stroke-linecap="round"
                                        stroke-linejoin="round" />
                                </g>
                            </svg>
                        </div>
                        <img class="opacity-70 hover:opacity-100 transition-opacity absolute w-full h-full object-cover dark:brightness-75 top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2"
                            src="/ins-rtm.jpg" />
                    </div>
                </div>
                <div class="flex grow py-4 px-2 sm:p-6">
                    <div class="grow">
                        <div class=" text-lg font-medium text-neutral-900 dark:text-neutral-100">
                            {{ __('Rubber thickness monitoring') }}
                        </div>
                        <div class="text-sm text-neutral-600 dark:text-neutral-400">
                            {{ __('Sistem monitoring ketebalan rubber yang terletak di proses pembuatan calendar.') }}
                        </div>
                    </div>
                </div>
            </div>
        </x-card-link>
        <x-card-link href="{{ route('insight', ['nav' => 'ldc']) }}">
            <div class="flex">
                <div>
                    <div class="relative flex w-32 h-full bg-neutral-200 dark:bg-neutral-700">
                        <div class="m-auto">
                            <svg xmlns="http://www.w3.org/2000/svg"
                                class="block h-16 w-auto text-neutral-800 dark:text-neutral-200 opacity-25"
                                viewBox="0 0 24.00 24.00" fill="none">
                                <g id="SVGRepo_bgCarrier" stroke-width="0" />
                                <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round" />
                                <g id="SVGRepo_iconCarrier">
                                    <path
                                        d="M3 13H8.5L10 15L12 9L14 13H17M6.2 19H17.8C18.9201 19 19.4802 19 19.908 18.782C20.2843 18.5903 20.5903 18.2843 20.782 17.908C21 17.4802 21 16.9201 21 15.8V8.2C21 7.0799 21 6.51984 20.782 6.09202C20.5903 5.71569 20.2843 5.40973 19.908 5.21799C19.4802 5 18.9201 5 17.8 5H6.2C5.0799 5 4.51984 5 4.09202 5.21799C3.71569 5.40973 3.40973 5.71569 3.21799 6.09202C3 6.51984 3 7.07989 3 8.2V15.8C3 16.9201 3 17.4802 3.21799 17.908C3.40973 18.2843 3.71569 18.5903 4.09202 18.782C4.51984 19 5.07989 19 6.2 19Z"
                                        stroke="#000000" stroke-width="0.8399999999999999" stroke-linecap="round"
                                        stroke-linejoin="round" />
                                </g>
                            </svg>
                        </div>
                        <img class="opacity-70 hover:opacity-100 transition-opacity absolute w-full h-full object-cover dark:brightness-75 top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2"
                            src="/ins-ldc.jpg" />
                    </div>
                </div>
                <div class="flex grow py-4 px-2 sm:p-6">
                    <div class="grow">
                        <div class=" text-lg font-medium text-neutral-900 dark:text-neutral-100">
                            {{ __('Pendataan kulit OKC') }}
                        </div>
                        <div class="text-sm text-neutral-600 dark:text-neutral-400">
                            {{ __('Sistem pencatatan hasil pemeriksaan kulit yang terletak di mesin nesting OKC.') }}
                        </div>
                    </div>
                </div>
            </div>
        </x-card-link>
    </div>
</x-app-layout>
