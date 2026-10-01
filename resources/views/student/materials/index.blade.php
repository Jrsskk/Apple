@extends('layouts.student')

@section('header', 'Materials')

@section('content')

<div class="mx-auto max-w-5xl">

    {{-- Page Header --}}
    <div class="mb-6">
        <div class="flex items-center gap-3">

            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                <svg
                    class="h-6 w-6"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.8"
                        d="M4 5a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5Zm9-2v6h5"
                    />
                </svg>
            </div>

            <div>
                <h2 class="text-xl font-bold text-slate-900">
                    Learning Materials
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    Access and download materials shared by your teachers.
                </p>
            </div>

        </div>
    </div>


    {{-- Material Count --}}
    <div class="mb-4 flex items-center justify-between">

        <div>
            <p class="text-sm font-semibold text-slate-800">
                Your Materials
            </p>

            <p class="text-xs text-slate-500">
                {{ $materials->total() }}
                {{ \Illuminate\Support\Str::plural('material', $materials->total()) }}
                available
            </p>
        </div>

    </div>


    {{-- Materials --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">

        @forelse($materials as $material)

            @php
                $fileType = strtolower((string) $material->file_type);

                $iconStyle = match (true) {
                    str_contains($fileType, 'pdf')
                        => ['bg-red-50', 'text-red-600'],

                    str_contains($fileType, 'video') ||
                    str_contains($fileType, 'mp4')
                        => ['bg-purple-50', 'text-purple-600'],

                    str_contains($fileType, 'image') ||
                    str_contains($fileType, 'jpg') ||
                    str_contains($fileType, 'png')
                        => ['bg-pink-50', 'text-pink-600'],

                    str_contains($fileType, 'presentation') ||
                    str_contains($fileType, 'ppt')
                        => ['bg-orange-50', 'text-orange-600'],

                    str_contains($fileType, 'document') ||
                    str_contains($fileType, 'doc')
                        => ['bg-blue-50', 'text-blue-600'],

                    default
                        => ['bg-indigo-50', 'text-indigo-600'],
                };

                $iconBg = $iconStyle[0];
                $iconText = $iconStyle[1];
            @endphp


            <article
                class="group flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-200 hover:shadow-md"
            >

                {{-- Card Top --}}
                <div class="p-4">

                    <div class="flex items-start gap-3">

                        {{-- File Icon --}}
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl {{ $iconBg }} {{ $iconText }}">

                            @if(
                                str_contains($fileType, 'video') ||
                                str_contains($fileType, 'mp4')
                            )

                                <svg
                                    class="h-6 w-6"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="1.8"
                                        d="m9 7 8 5-8 5V7Z"
                                    />
                                </svg>

                            @elseif(
                                str_contains($fileType, 'image') ||
                                str_contains($fileType, 'jpg') ||
                                str_contains($fileType, 'png')
                            )

                                <svg
                                    class="h-6 w-6"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <rect
                                        x="4"
                                        y="4"
                                        width="16"
                                        height="16"
                                        rx="2"
                                        stroke-width="1.8"
                                    />
                                    <circle
                                        cx="9"
                                        cy="9"
                                        r="1.5"
                                        stroke-width="1.8"
                                    />
                                    <path
                                        stroke-width="1.8"
                                        d="m5 17 4-4 3 3 2-2 5 5"
                                    />
                                </svg>

                            @else

                                <svg
                                    class="h-6 w-6"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-width="1.8"
                                        d="M4 5a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5Zm9-2v6h5"
                                    />
                                </svg>

                            @endif

                        </div>


                        {{-- Material Info --}}
                        <div class="min-w-0 flex-1">

                            <h3
                                class="truncate text-sm font-bold text-slate-800"
                                title="{{ $material->title }}"
                            >
                                {{ $material->title }}
                            </h3>

                            <p class="mt-1 truncate text-xs text-slate-500">
                                {{ $material->schoolClass?->display_name ?? 'Class material' }}
                            </p>

                        </div>

                    </div>


                    {{-- File Type --}}
                    <div class="mt-4 flex items-center gap-2">

                        <span class="rounded-full {{ $iconBg }} px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide {{ $iconText }}">
                            {{ $material->file_type }}
                        </span>

                        <span class="text-xs text-slate-400">
                            Learning material
                        </span>

                    </div>

                </div>


                {{-- Actions --}}
                <div class="mt-auto border-t border-slate-100 bg-slate-50/60 p-3">

                    <div class="grid grid-cols-2 gap-2">

                        <a
                            href="{{ route('student.materials.file', $material) }}"
                            class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs font-semibold text-slate-700 transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700 active:scale-[0.98]"
                        >

                            <svg
                                class="h-4 w-4"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-width="1.8"
                                    d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"
                                />
                                <circle
                                    cx="12"
                                    cy="12"
                                    r="2.5"
                                    stroke-width="1.8"
                                />
                            </svg>

                            Open

                        </a>


                        <a
                            href="{{ route('student.materials.file', ['material' => $material, 'download' => 1]) }}"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-3 py-2.5 text-xs font-semibold text-white shadow-sm transition hover:bg-indigo-700 active:scale-[0.98]"
                        >

                            <svg
                                class="h-4 w-4"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-width="1.8"
                                    d="M12 3v12m0 0 4-4m-4 4-4-4M5 20h14"
                                />
                            </svg>

                            Download

                        </a>

                    </div>

                </div>

            </article>

        @empty

            {{-- Empty State --}}
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center sm:col-span-2 lg:col-span-3">

                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-500">

                    <svg
                        class="h-8 w-8"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-width="1.8"
                            d="M4 5a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5Zm9-2v6h5"
                        />
                    </svg>

                </div>

                <h3 class="mt-4 font-bold text-slate-800">
                    No materials yet
                </h3>

                <p class="mx-auto mt-1 max-w-sm text-sm leading-relaxed text-slate-500">
                    Materials shared by your teachers will appear here.
                    Check back later for new learning resources.
                </p>

            </div>

        @endforelse

    </div>


    {{-- Pagination --}}
    @if($materials->hasPages())

        <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
            {{ $materials->links() }}
        </div>

    @endif

</div>

@endsection