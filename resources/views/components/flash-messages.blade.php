@if (session('success'))
    <div class="rounded-md bg-green-50 border border-green-200 p-4">
        <div class="flex">
            <svg class="size-5 text-green-500 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="ms-3 text-sm font-medium text-green-800">{{ session('success') }}</p>
        </div>
    </div>
@endif

@if (session('error'))
    <div class="rounded-md bg-red-50 border border-red-200 p-4">
        <div class="flex">
            <svg class="size-5 text-red-500 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
            </svg>
            <p class="ms-3 text-sm font-medium text-red-800">{{ session('error') }}</p>
        </div>
    </div>
@endif

@if (session('warning'))
    <div class="rounded-md bg-yellow-50 border border-yellow-200 p-4">
        <div class="flex">
            <svg class="size-5 text-yellow-500 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
            </svg>
            <p class="ms-3 text-sm font-medium text-yellow-800">{{ session('warning') }}</p>
        </div>
    </div>
@endif

@if (session('info'))
    <div class="rounded-md bg-blue-50 border border-blue-200 p-4">
        <div class="flex">
            <svg class="size-5 text-blue-500 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
            </svg>
            <p class="ms-3 text-sm font-medium text-blue-800">{{ session('info') }}</p>
        </div>
    </div>
@endif
