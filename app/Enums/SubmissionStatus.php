<?php

namespace App\Enums;

enum SubmissionStatus: string
{
    case NotStarted = 'not_started';
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Late = 'late';
    case Graded = 'graded';
    case Returned = 'returned';
}
