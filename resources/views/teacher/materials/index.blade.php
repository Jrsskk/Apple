@extends('layouts.teacher')

@section('header', 'Learning Materials')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
        <h1 class="text-2xl font-bold text-slate-900">Learning Materials</h1>
        <div class="mt-4 sm:mt-0">
            <a href="{{ route('teacher.materials.create') }}"
               class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-emerald-600 hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500">
                <svg class="-mr-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 010-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                </svg>
                Add New Material
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">
            {{ session('error') }}
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Title</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Class</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Size</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-slate-200">
                    @forelse($materials as $material)
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4 font-medium text-slate-900 whitespace-nowrap">
                                {{ $material->title }}
                            </td>
                            <td class="px-6 py-4 text-slate-700 whitespace-nowrap">
                                {{ $material->schoolClass?->display_name ?? 'N/A' }}
                            </td>
                            <td class="px-6 py-4 text-slate-700 whitespace-nowrap">
                                <!-- File type icon -->
                                <span class="inline-flex items-center">
                                    @php
                                        $icon = match(strtolower($material->file_type ?? '')) {
                                            'pdf' => '<svg class="h-4 w-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m2 0a2 2 0 100-4 2 2 0 000-4 2 2 0 000-4zm0 6a2 2 0 1000-4 2 2 0 000-4zm0 6a2 2 0 1000-4 2 2 0 000-4z"></path></svg>',
                                            'doc' => '<svg class="h-4 w-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m2 0a2 2 0 100-4 2 2 0 000-4 2 2 0 000-4zm0 6a2 2 0 100-4 2 2 0 000-4zm0 6a2 2 0 100-4 2 2 0 000-4z"></path></svg>',
                                            default => '<svg class="h-4 w-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m2 0a2 2 0 100-4 2 2 0 000-4 2 2 0 000-4zm0 6a2 2 0 100-4 2 2 0 000-4zm0 6a2 2 0 100-4 2 2 0 000-4z"></path></svg>'
                                        };
                                    @endphp
                                    {!! $icon !!}

                                    <span class="ml-2 text-capitalize">{{ ucfirst($material->file_type ?? 'file') }}</span>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-slate-700 whitespace-nowrap">
                                @php
                                    $size = $material->file_size ?? 0;
                                    if ($size >= 1073741824) {
                                        $sizeDisplay = number_format($size / 1073741824, 2) . ' GB';
                                    } elseif ($size >= 1048576) {
                                        $sizeDisplay = number_format($size / 1048576, 2) . ' MB';
                                    } elseif ($size >= 1024) {
                                        $sizeDisplay = number_format($size / 1024, 2) . ' KB';
                                    } else {
                                        $sizeDisplay = $size . ' B';
                                    }
                                @endphp
                                {{ $sizeDisplay }}
                            </td>
                            <td class="px-6 py-4 text-right text-sm font-medium space-x-2">
                                <!-- Download Button -->
                                <a href="{{ route('teacher.materials.download', $material->id) }}"
                                   class="text-indigo-600 hover:text-indigo-900">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M5 10l5 5 5-5"/>
                                    </svg>
                                </a>

                                <!-- Replace Button -->
                                <a href="{{ route('teacher.materials.replace', $material->id) }}"
                                   class="text-yellow-600 hover:text-yellow-900">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 0l-4-4 4 4M7 16h5"/>
                                    </svg>
                                </a>

                                <!-- Delete Button -->
                                <form action="{{ route('teacher.materials.destroy', $material->id) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this material?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="text-red-600 hover:text-red-900 px-2 py-1 rounded">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-500">
                                No materials uploaded yet.
                                <a href="{{ route('teacher.materials.create') }}"
                                   class="ml-4 text-indigo-600 hover:text-indigo-900 underline">
                                    Add your first material
                                </a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection