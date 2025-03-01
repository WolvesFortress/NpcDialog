<?php

declare(strict_types=1);

namespace NpcDialog;

enum ButtonType: int{
	case URL = 0;
	case COMMAND = 1;
	case INVALID = 2;
}
