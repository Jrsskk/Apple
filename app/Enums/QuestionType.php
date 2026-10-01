<?php

namespace App\Enums;

enum QuestionType: string
{
    case MultipleChoice = 'multiple_choice';
    case TrueFalse = 'true_false';
    case Identification = 'identification';
    case Matching = 'matching';
    case Sequencing = 'sequencing';
    case ImageBased = 'image_based';
    case MultipleSelection = 'multiple_selection';
    case DragDrop = 'drag_drop';
    case Essay = 'essay';
}
