@extends('layouts.teacher')

@section('header', 'Learning Materials')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header Section -->
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="min-w-0 flex-1">
            <h1 class="text-2xl font-bold leading-7 text-slate-900 sm:truncate sm:text-3xl sm:tracking-tight">
                Learning Materials
            </h1>
            <p class="mt-1 text-sm text-slate-500">
                Manage and share course resources, documents, and files with your classes.
            </p>
        </div>
        <div class="mt-4 flex md:ml-4 md:mt-0">
            <a href="{{ route('teacher.materials.create') }}"
               class="inline-flex items-center gap-x-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 transition-all duration-150">
                <svg class="-ml-0.5 h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 010-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                </svg>
                Add New Material
            </a>
        </div>
    </div>

    <!-- Session Feedback Alerts -->
    @if(session('success'))
        <div class="mb-6 rounded-xl bg-emerald-50 p-4 border border-emerald-200/80 shadow-sm">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-emerald-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-emerald-800">{{ session('success') }}</p>
                </div>
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="mb-6 rounded-xl bg-rose-50 p-4 border border-rose-200/80 shadow-sm">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-rose-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-rose-800">{{ session('error') }}</p>
                </div>
            </div>
        </div>
    @endif

    <!-- Data Table Container -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                        <th scope="col" class="px-6 py-4">Title</th>
                        <th scope="col" class="px-6 py-4">Class</th>
                        <th scope="col" class="px-6 py-4">Type</th>
                        <th scope="col" class="px-6 py-4">Size</th>
                        <th scope="col" class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white text-sm">
                    @forelse($materials as $material)
                        <tr class="hover:bg-slate-50/60 transition-colors duration-150">
                            <!-- Title -->
                            <td class="px-6 py-4 font-semibold text-slate-900 whitespace-nowrap">
                                <div class="flex items-center gap-x-3">
                                    <span class="truncate max-w-xs sm:max-w-md" title="{{ $material->title }}">
                                        {{ $material->title }}
                                    </span>
                                </div>
                            </td>

                            <!-- Class -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-slate-100 text-slate-700 ring-1 ring-inset ring-slate-500/10">
                                    {{ $material->schoolClass?->display_name ?? 'Unassigned' }}
                                </span>
                            </td>

                            <!-- Type -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                @php
                                    $ext = strtolower($material->file_type ?? '');
                                    $badgeStyle = match(true) {
                                        in_array($ext, ['pdf']) => 'bg-rose-50 text-rose-700 ring-rose-600/20',
                                        in_array($ext, ['doc', 'docx']) => 'bg-blue-50 text-blue-700 ring-blue-700/10',
                                        in_array($ext, ['xls', 'xlsx', 'csv']) => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
                                        in_array($ext, ['ppt', 'pptx']) => 'bg-amber-50 text-amber-700 ring-amber-600/20',
                                        in_array($ext, ['png', 'jpg', 'jpeg', 'svg', 'webp']) => 'bg-purple-50 text-purple-700 ring-purple-700/10',
                                        default => 'bg-slate-50 text-slate-700 ring-slate-600/10'
                                    };
                                @endphp
                                <span class="inline-flex items-center gap-x-1.5 px-2.5 py-1 rounded-md text-xs font-medium ring-1 ring-inset {{ $badgeStyle }}">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                    </svg>
                                    {{ strtoupper($material->file_type ?? 'FILE') }}
                                </span>
                            </td>

                            <!-- Size -->
                            <td class="px-6 py-4 whitespace-nowrap text-slate-500 text-xs font-mono">
                                @php
                                    $bytes = $material->file_size ?? 0;
                                    if ($bytes >= 1073741824) {
                                        $sizeDisplay = number_format($bytes / 1073741824, 2) . ' GB';
                                    } elseif ($bytes >= 1048576) {
                                        $sizeDisplay = number_format($bytes / 1048576, 1) . ' MB';
                                    } elseif ($bytes >= 1024) {
                                        $sizeDisplay = number_format($bytes / 1024, 0) . ' KB';
                                    } else {
                                        $sizeDisplay = $bytes . ' B';
                                    }
                                @endphp
                                {{ $sizeDisplay }}
                            </td>

                            <!-- Actions -->
                            <td class="px-6 py-4 whitespace-nowrap text-right text-xs">
                                <div class="flex items-center justify-end space-x-1">
                                    <!-- Download -->
                                    <a href="{{ route('teacher.materials.download', $material->id) }}"
                                       title="Download"
                                       class="p-2 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors duration-150">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M12 4v12m0 0l-4-4m4 4l4-4"/>
                                        </svg>
                                    </a>

                                    <!-- Replace -->
                                    <a href="{{ route('teacher.materials.replace', $material->id) }}"
                                       title="Replace File"
                                       class="p-2 text-slate-400 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-colors duration-150">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                        </svg>
                                    </a>

                                    <!-- Delete -->
                                    <form action="{{ route('teacher.materials.destroy', $material->id) }}" 
                                          method="POST" 
                                          class="inline-block" 
                                          onsubmit="return confirm('Are you sure you want to delete this material?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                title="Delete Material"
                                                class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors duration-150">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-16 text-center">
                                <div class="max-w-sm mx-auto flex flex-col items-center">
                                    <div class="h-12 w-12 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mb-4">
                                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </div>
                                    <h3 class="text-sm font-semibold text-slate-900">No materials uploaded</h3>
                                    <p class="mt-1 text-xs text-slate-500">Get started by uploading your first learning resource for your students.</p>
                                    <div class="mt-5">
                                        <a href="{{ route('teacher.materials.create') }}" 
                                           class="inline-flex items-center gap-x-1.5 text-xs font-semibold text-emerald-600 hover:text-emerald-500">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                            </svg>
                                            Upload First Material
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection