import { Head, Link } from '@inertiajs/react';
import TeacherLayout from '@/Layouts/TeacherLayout';
import { formatDateTime } from '@/utils/teacher';

export default function QuizAttempt({ attempt }) {
    return (
        <TeacherLayout title="Quiz Attempt Review">
            <Head title="Quiz Attempt" />

            <div className="mb-4">
                <Link href="/teacher/submissions" className="text-sm text-emerald-600">← Back to submissions</Link>
            </div>

            <div className="bg-white rounded-xl border p-6 mb-6">
                <h2 className="font-semibold">{attempt.quiz?.title}</h2>
                <p className="text-sm text-slate-500 mt-1">
                    {attempt.student?.first_name} {attempt.student?.last_name} · {formatDateTime(attempt.submitted_at)}
                </p>
                <p className="text-lg font-bold mt-2">Score: {attempt.score}/{attempt.total_points} ({attempt.percentage}%)</p>
            </div>

            <div className="space-y-4">
                {attempt.answers?.map((answer, i) => (
                    <div key={answer.id} className={`border rounded-xl p-4 ${answer.is_correct ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50'}`}>
                        <p className="font-medium text-sm">Q{i + 1}. {answer.question?.question_text}</p>
                        <p className="text-xs text-slate-500 mt-1">{answer.question?.type?.value ?? answer.question?.type} · {answer.points_earned}/{answer.question?.points} pts</p>
                        {answer.answer_text && <p className="text-sm mt-2">Answer: {answer.answer_text}</p>}
                        {answer.selected_options && <p className="text-sm mt-2">Selected: {JSON.stringify(answer.selected_options)}</p>}
                    </div>
                ))}
            </div>
        </TeacherLayout>
    );
}
