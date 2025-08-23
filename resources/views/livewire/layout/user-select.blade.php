<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

use App\Models\User;
use Livewire\Attributes\On;
use Illuminate\Database\Eloquent\Builder;

new #[Layout("layouts.app")] class extends Component {
    public $users = [];

    #[On("userq-updated")]
    public function search($userq)
    {
        if ($userq) {
            $this->users = User::where(function (Builder $query) use ($userq) {
                $query->orWhere("name", "LIKE", "%" . $userq . "%")->orWhere("emp_id", "LIKE", "%" . $userq . "%");
            })
                ->where("is_active", 1)
                ->orderBy("seen_at", "desc")
                ->limit(10)
                ->get();
        } else {
            $this->users = [];
        }
    }
};

?>

<div>
    @if (count($users))
        <div class="bg-white dark:bg-neutral-800 shadow-lg sm:rounded-lg overflow-y-scroll max-h-72">
            @foreach ($users as $user)
                <button
                    type="button"
                    class="flex p-3 text-left w-full hover:bg-caldy-500/10 active:bg-caldy-500/30"
                    x-on:click="
                        $dispatch('user-selected', {
                            user_id: '{{ $user->id }}',
                            user_name: '{{ $user->name }}',
                            user_emp_id: '{{ $user->emp_id }}',
                            user_photo: '{{ $user->photo }}',
                            user_is_active: '{{ $user->is_active }}',
                            user_seen_at: '{{ $user->seen_at }}',
                        })
                    "
                >
                    <div>
                        <div class="w-8 h-8 my-auto mr-3 bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">
                            @if ($user->photo)
                                <img class="w-full h-full object-cover dark:brightness-75" src="{{ "/storage/users/" . $user->photo }}" />
                            @else
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="block fill-current text-neutral-800 dark:text-neutral-200 opacity-25"
                                    viewBox="0 0 1000 1000"
                                    xmlns:v="https://vecta.io/nano"
                                >
                                    <path
                                        d="M621.4 609.1c71.3-41.8 119.5-119.2 119.5-207.6-.1-132.9-108.1-240.9-240.9-240.9s-240.8 108-240.8 240.8c0 88.5 48.2 165.8 119.5 207.6-147.2 50.1-253.3 188-253.3 350.4v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c0-174.9 144.1-317.3 321.1-317.3S821 784.4 821 959.3v3.8a26.63 26.63 0 0 0 26.7 26.7c14.8 0 26.7-12 26.7-26.7v-3.8c.2-162.3-105.9-300.2-253-350.2zM312.7 401.4c0-103.3 84-187.3 187.3-187.3s187.3 84 187.3 187.3-84 187.3-187.3 187.3-187.3-84.1-187.3-187.3z"
                                    />
                                </svg>
                            @endif
                        </div>
                    </div>
                    <div>
                        <div>{{ $user->name }}</div>
                        <div class="text-xs text-neutral-400 dark:text-neutral-600">{{ $user->emp_id }}</div>
                    </div>
                </button>
            @endforeach
        </div>
    @endif
</div>
