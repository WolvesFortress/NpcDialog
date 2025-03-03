<?php
/**
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

declare(strict_types=1);

namespace NpcDialog;

use Closure;
use DaveRandom\CallbackValidator\CallbackType;
use DaveRandom\CallbackValidator\InvalidCallbackException;
use DaveRandom\CallbackValidator\ParameterType;
use DaveRandom\CallbackValidator\ReturnType;
use JsonSerializable;
use pocketmine\player\Player;
use pocketmine\utils\Utils;
use TypeError;

class Button implements JsonSerializable{

	/** @phpstan-var list<mixed>|null */
	private ?array $data = [];

	private ButtonMode $mode = ButtonMode::BUTTON;

	private ButtonType $type = ButtonType::COMMAND;

	/** @var null|Closure(Player $player) : bool $submitListener */
	private ?Closure $submitListener;

	/** @throws TypeError|InvalidCallbackException */
	public function __construct(private string $name = "", private string $command = "", ?Closure $submitListener = null){
		$this->setSubmitListener($submitListener);
	}

	public function getName() : string{
		return $this->name;
	}

	/** @return $this */
	public function setName(string $name) : self{
		$this->name = $name;
		return $this;
	}

	public function getCommand() : string{ return $this->command; }

	/** @return $this */
	public function setCommand(string $command) : self{
		$this->command = $command;
		return $this;
	}

	public function getMode() : ButtonMode{ return $this->mode; }

	/** @return $this */
	public function setMode(ButtonMode $mode = ButtonMode::BUTTON) : self{
		$this->mode = $mode;
		return $this;
	}

	public function getType() : ButtonType{ return $this->type; }

	/** @return $this */
	public function setType(ButtonType $type = ButtonType::COMMAND) : self{
		$this->type = $type;
		return $this;
	}

	public function getData() : ?array{ return $this->data; }

	/** @return $this */
	public function setData(?array $data) : Button{
		$this->data = $data;
		return $this;
	}

	public function getSubmitListener() : ?Closure{
		return $this->submitListener;
	}

	/**
	 * @param null|Closure(Player $player) : bool $submitListener
	 *
	 * @return $this
	 * @throws InvalidCallbackException|TypeError
	 */
	public function setSubmitListener(?Closure $submitListener) : self{
		if($submitListener !== null){
			Utils::validateCallableSignature(new CallbackType(
				new ReturnType("bool"),
				new ParameterType("player", Player::class)
			), $submitListener);
		}

		$this->submitListener = $submitListener;
		return $this;
	}

	public function executeSubmitListener(Player $player) : bool{
		if($this->submitListener !== null){
			return ($this->submitListener)($player);
		}
		return false;
	}

	public function jsonSerialize() : array{
		return [
			"button_name" => $this->name,//the name of the button is only set if mode is 0 (button)
			"data" => $this->data,//the data of the button is null when type is url
			"mode" => $this->mode,//0 = button, 1 = on close, 2 = on open
			"text" => $this->command,//the text in the command field @see https://github.com/refteams/libNpcDialogue/blob/pm5/main/src/ref/libNpcDialogue/form/NpcDialogueButtonData.php#L55C3-L58C28
//			$this->data = array_map(static fn($str) => [
//				"cmd_line" => $str,
//				"cmd_ver" => self::CMD_VER // 17 in 1.18.0.2.0, and 12 in 1.16.0.2.0
//			], explode("\n", $text));
			"type" => $this->type//always 1 (command) when not education edition
		];
	}
}