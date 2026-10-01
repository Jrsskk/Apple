export const QUESTION_TYPES = [
    { value: 'multiple_choice', label: 'Multiple Choice' },
    { value: 'true_false', label: 'True or False' },
    { value: 'identification', label: 'Identification' },
    { value: 'matching', label: 'Matching Type' },
    { value: 'sequencing', label: 'Sequencing' },
    { value: 'drag_drop', label: 'Drag and Drop' },
    { value: 'image_based', label: 'Image-Based' },
    { value: 'multiple_selection', label: 'Multiple Selection' },
];

export function emptyQuestion(type = 'multiple_choice') {
    const base = {
        type,
        question_text: '',
        points: 1,
        explanation: '',
        image_path: null,
        options: [],
    };

    switch (type) {
        case 'true_false':
            return {
                ...base,
                options: [
                    { option_text: 'True', is_correct: true, match_key: null },
                    { option_text: 'False', is_correct: false, match_key: null },
                ],
            };
        case 'identification':
            return {
                ...base,
                options: [{ option_text: '', is_correct: true, match_key: null }],
            };
        case 'matching':
            return {
                ...base,
                options: [
                    { option_text: 'Term A', is_correct: false, match_key: 'pair_1' },
                    { option_text: 'Definition A', is_correct: true, match_key: 'pair_1' },
                    { option_text: 'Term B', is_correct: false, match_key: 'pair_2' },
                    { option_text: 'Definition B', is_correct: true, match_key: 'pair_2' },
                ],
            };
        case 'sequencing':
        case 'drag_drop':
            return {
                ...base,
                options: [
                    { option_text: 'Step 1', is_correct: true, match_key: '1', order: 0 },
                    { option_text: 'Step 2', is_correct: true, match_key: '2', order: 1 },
                    { option_text: 'Step 3', is_correct: true, match_key: '3', order: 2 },
                ],
            };
        case 'image_based':
            return {
                ...base,
                options: [
                    { option_text: 'Option A', is_correct: true, match_key: null },
                    { option_text: 'Option B', is_correct: false, match_key: null },
                ],
            };
        default:
            return {
                ...base,
                options: [
                    { option_text: '', is_correct: true, match_key: null },
                    { option_text: '', is_correct: false, match_key: null },
                ],
            };
    }
}

export function formatDateTime(value) {
    if (!value) return '—';
    return new Date(value).toLocaleString();
}

export function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleDateString();
}

export function statusBadge(status) {
    const colors = {
        draft: 'bg-slate-100 text-slate-700',
        published: 'bg-emerald-100 text-emerald-700',
        archived: 'bg-amber-100 text-amber-700',
        submitted: 'bg-blue-100 text-blue-700',
        graded: 'bg-green-100 text-green-700',
        late: 'bg-red-100 text-red-700',
        pending: 'bg-yellow-100 text-yellow-700',
        synced: 'bg-emerald-100 text-emerald-700',
        failed: 'bg-red-100 text-red-700',
    };
    return colors[status] || 'bg-slate-100 text-slate-700';
}
