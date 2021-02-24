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

namespace kim\present\batchfarming\entity;

use pocketmine\entity\object\ItemEntity;
use pocketmine\event\inventory\InventoryPickupItemEvent;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;

/**
 * An entity that functions like an item entity.
 * Re-written for return immediately to the owner player.
 * When save into chunk, it is saved as an ItemEntity.
 */
final class ReturnItemEntity extends ItemEntity{
    /** @override for auto pickup to owning player */
    protected function entityBaseTick(int $tickDiff = 1) : bool{
        if($this->closed){
            return false;
        }

        $hasUpdate = parent::entityBaseTick($tickDiff);

        if(!$this->isFlaggedForDespawn() && $this->pickupDelay === 0){
            $player = $this->getOwningEntity();
            if($player instanceof Player){
                $this->onCollideWithPlayer($player);
                $hasUpdate = true;
            }
        }

        return $hasUpdate;
    }

    /** @override for save with chunk like default item entity */
    public function saveNBT() : CompoundTag{
        return (new ItemEntity($this->location, $this->item))->saveNBT();
    }

    /** @override for prevent pickup when player has infinite resources (ex, creative-mode) */
    public function onCollideWithPlayer(Player $player) : void{
        if($this->getPickupDelay() !== 0){
            return;
        }

        if($player->hasFiniteResources()){
            $item = $this->getItem();
            $playerInventory = $player->getInventory();

            if(!$playerInventory->canAddItem($item))
                return;

            $ev = new InventoryPickupItemEvent($playerInventory, $this);
            $ev->call();
            if($ev->isCancelled())
                return;

            $playerInventory->addItem(clone $item);
        }

        foreach($this->getViewers() as $viewer){
            $viewer->getNetworkSession()->onPlayerPickUpItem($player, $this);
        }
        $this->flagForDespawn();
    }
}