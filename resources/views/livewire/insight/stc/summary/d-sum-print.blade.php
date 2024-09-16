<div id="print-container" class="w-[1200px] mx-auto p-8 aspect-[297/210] bg-white text-neutral-900 cal-offscreen">
    <div class="flex flex-col gap-6 w-full h-full">
        <div class="grow-0">
            <div id="print-container-header">
                <div class="flex gap-x-6 justify-between">            
                    <div class="flex flex-col">
                        <dt class="mb-3 text-neutral-500 text-xs uppercase">{{ __('Informasi pengukuran') }}</dt>
                        <dd>
                            <table>
                                <tr>
                                    <td class="text-neutral-500 text-xs">
                                        {{ __('Urutan') }}
                                    </td>
                                    <td class="px-1">:</td>
                                    <td>
                                        -
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-neutral-500 text-xs">
                                        {{ __('Pengukur 1') }}
                                    </td>
                                    <td class="px-1">:</td>
                                    <td>
                                        -
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-neutral-500 text-xs">
                                        {{ __('Pengukur 2') }}
                                    </td>
                                    <td class="px-1">:</td>
                                    <td>
                                        -
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-neutral-500 text-xs">
                                        {{ __('Kode alat ukur') }}
                                    </td>
                                    <td class="px-1">:</td>
                                    <td>
                                        -
                                    </td>
                                </tr>
                            </table>
                        </dd>
                    </div>            
                    <div class="flex flex-col">
                        <dt class="mb-3 text-neutral-500 text-xs uppercase">{{ __('Informasi mesin') }}</dt>
                        <dd>
                            <table>
                                <tr>
                                    <td class="text-neutral-500 text-xs">
                                        {{ __('Line') }}
                                    </td>
                                    <td class="px-1">:</td>
                                    <td>
                                        -
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-neutral-500 text-xs">
                                        {{ __('Mesin') }}
                                    </td>
                                    <td class="px-1">:</td>
                                    <td>
                                        -
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-neutral-500 text-xs">
                                        {{ __('Posisi') }}
                                    </td>
                                    <td class="px-1">:</td>
                                    <td>
                                        -
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-neutral-500 text-xs">
                                        {{ __('Kecepatan') }}
                                    </td>
                                    <td class="px-1">:</td>
                                    <td>
                                        -
                                    </td>
                                </tr>
                            </table>
                        </dd>
                    </div> 
                    <div class="flex flex-col">
                        <dt class="mb-3 text-neutral-500 text-xs uppercase">{{ __('Suhu diatur') }}</dt>
                        <dd>
                            <div class="grid grid-cols-8 text-center gap-x-6">
                                <div>
                                    <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400">{{ __('Zona') }}</div>
                                    <div>-</div>
                                </div>
                                <div>
                                    <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400">{{ __('Zona') }}</div>
                                    <div>-</div>
                                </div>
                                <div>
                                    <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400">{{ __('Zona') }}</div>
                                    <div>-</div>
                                </div>
                                <div>
                                    <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400">{{ __('Zona') }}</div>
                                    <div>-</div>
                                </div>
                                <div>
                                    <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400">{{ __('Zona') }}</div>
                                    <div>-</div>
                                </div>
                                <div>
                                    <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400">{{ __('Zona') }}</div>
                                    <div>-</div>
                                </div>
                                <div>
                                    <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400">{{ __('Zona') }}</div>
                                    <div>-</div>
                                </div>
                                <div>
                                    <div class="mb-1 text-xs uppercase font-normal leading-none text-neutral-400">{{ __('Zona') }}</div>
                                    <div>-</div>
                                </div>
                            </div>
                        </dd>
                    </div>
                </div>
            </div>
        </div>
        <div class="grow border border-neutral-500 rounded-lg overflow-hidden">
            <div id="print-chart-container" wire:key="print-chart-container" wire:ignore></div>
        </div>
        <div class="grow-0">
            <div id="print-container-footer">
                <div class="flex justify-between p-4">
                    <div class="flex justify-between flex-col">
                        <div>{{ __('Zona 1') . ': 70-80 °C' }}</div>
                        <div>{{ __('Zona 2') . ': 60-70 °C' }}</div>
                        <div>{{ __('Zona 3') . ': 50-60 °C' }}</div>
                        <div>{{ __('Zona 4') . ': 40-50 °C' }}</div>
                    </div>
                    <div class="flex gap-x-3">
                        <div>
                            <div class="text-center font-bold">CE</div>
                            <div class="flex justify-center">
                                <div class="w-8 h-8 my-4 bg-neutral-200 rounded-full overflow-hidden">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="block fill-current text-neutral-800  opacity-25" viewBox="0 0 1000 1000" xmlns:v="https://vecta.io/nano"><path d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z"/></svg>
                                </div>
                            </div>
                            <hr class="border-neutral-300 w-48">
                            <div class="text-center">
                                <div class="text-xs">-</div>
                            </div>
                        </div>    
                        <div>
                            <div class="text-center font-bold">TL</div>
                            <div class="grow">
                                <div class="w-8 h-8 my-4"></div>
                            </div>
                            <hr class="border-neutral-300 w-48">
                            <div class="text-center text-xs text-neutral-500">{{ __('Nama dan tanggal')}}</div>
                        </div> 
                        <div>
                            <div class="text-center font-bold">GL</div>
                            <div><div class="w-8 h-8 my-4"></div></div>
                            <hr class="border-neutral-300 w-48">
                            <div class="text-center text-xs text-neutral-500">{{ __('Nama dan tanggal')}}</div>
                        </div> 
                        <div>
                            <div class="text-center font-bold">VSM</div>
                            <div><div class="w-8 h-8 my-4"></div></div>
                            <hr class="border-neutral-300 w-48">
                            <div class="text-center text-xs text-neutral-500">{{ __('Nama dan tanggal')}}</div>
                        </div>             
                    </div>
                </div>
            </div> 
        </div>
    </div>
</div>