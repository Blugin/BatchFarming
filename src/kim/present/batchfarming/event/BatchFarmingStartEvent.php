<?php

/**
 *  ____                           _   _  ___
 * |  _ \ _ __ ___  ___  ___ _ __ | |_| |/ (_)_ __ ___
 * | |_) | '__/ _ \/ __|/ _ \ '_ \| __| ' /| | '_ ` _ \
 * |  __/| | |  __/\__ \  __/ | | | |_| . \| | | | | | |
 * |_|   |_|  \___||___/\___|_| |_|\__|_|\_\_|_| |_| |_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author  PresentKim (debe3721@gmail.com)
 * @link    https://github.com/PresentKim
 * @license https://www.gnu.org/licenses/lgpl-3.0 LGPL-3.0 License
 *
 *   (\ /)
 *  ( . .) ♥
 *  c(")(")
 *
 * @noinspection PhpIllegalPsrClassPathInspection
 * @noinspection PhpDocSignatureInspection
 * @noinspection SpellCheckingInspection
 */

declare(strict_types=1);

namespace kim\present\batchfarming\event;

use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\event\Event;
use pocketmine\item\Item;
use pocketmine\player\Player;

final class BatchFarmingStartEvent extends Event implements Cancellable{
    use CancellableTrait;

    private Player $player;
    private Item $item;

    private int $maxStep;
    private float $risePerStep;
    private bool $clockwise;

    public function __construct(Player $player, Item $item, int $maxStep, float $risePerStep, bool $clockwise){
        $this->player = $player;
        $this->item = $item;
        $this->maxStep = $maxStep;
        $this->risePerStep = $risePerStep;
        $this->clockwise = $clockwise;
    }

    public function getPlayer() : Player{
        return $this->player;
    }

    public function getItem() : Item{
        return $this->item;
    }

    public function getMaxStep() : int{
        return $this->maxStep;
    }

    public function setMaxStep(int $maxStep) : void{
        $this->maxStep = $maxStep;
    }

    public function getRisePerStep() : float{
        return $this->risePerStep;
    }

    public function setRisePerStep(float $risePerStep) : void{
        $this->risePerStep = $risePerStep;
    }

    public function isClockwise() : bool{
        return $this->clockwise;
    }

    public function setClockwise(bool $clockwise) : void{
        $this->clockwise = $clockwise;
    }
}