<?php

declare(strict_types=1);

namespace NpcDialog;

enum ButtonMode: int{
	case BUTTON = 0;
	case ON_CLOSE = 1;
	case ON_OPEN = 2;
}
