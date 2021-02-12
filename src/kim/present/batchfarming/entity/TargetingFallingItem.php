<?php /** @noinspection PhpInternalEntityUsedInspection */
declare(strict_types=1);

namespace kim\present\batchfarming\entity;

use pocketmine\block\Block;
use pocketmine\entity\Location;
use pocketmine\entity\object\ItemEntity;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Item;
use pocketmine\item\ItemUseResult;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\AddItemActorPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use pocketmine\world\World;

/**
 * An entity that functions like an item entity.
 *
 * @see TargetingFallingEntity
 * @see ItemEntity
 */
final class TargetingFallingItem extends TargetingFallingEntity{
    private Item $item;

    public function __construct(Location $location, Player $owningPlayer, Item $item, int $targetY, ?CompoundTag $nbt = null){
        parent::__construct($location, $owningPlayer, $targetY, $nbt);
        $this->item = (clone $item)->setCount(1);
    }

    /** @override for spawn to falling block */
    public static function getNetworkTypeId() : string{
        return EntityIds::ITEM;
    }

    /** @override for spawn to item entity */
    protected function sendSpawnPacket(Player $player) : void{
        $pk = new AddItemActorPacket();
        $pk->entityRuntimeId = $this->getId();
        $pk->position = $this->location->asVector3();
        $pk->motion = $this->getMotion();
        $pk->item = TypeConverter::getInstance()->coreItemStackToNet($this->getItem());
        $pk->metadata = $this->getAllNetworkData();

        $player->getNetworkSession()->sendDataPacket($pk);
    }

    /** Returns item */
    public function getItem() : Item{
        return clone $this->item;
    }

    /**
     * Use bonemeal to given vector
     * It works like World::useItemOn(), but only includes PlayerInteractEvent
     *
     * @see World::useItemOn()
     */
    protected function onRun(World $world, Block $blockClicked, Block $blockReplace) : bool{
        $blockClicked = $blockClicked->getSide(Facing::UP);
        $blockReplace = $blockClicked->getSide(Facing::UP);

        $clickVector = new Vector3(0.5, 0, 0.5);
        $item = $this->getItem();

        $ev = new PlayerInteractEvent($this->owningPlayer, $item, $blockClicked, $clickVector, Facing::UP, PlayerInteractEvent::RIGHT_CLICK_BLOCK);
        if($this->owningPlayer->isSpectator()){
            $ev->cancel();
        }

        $ev->call();
        if($ev->isCancelled())
            return false;

        if(!$item->isNull() && $blockClicked->onInteract($item, Facing::UP, $clickVector, $this->owningPlayer))
            return true;

        return $item->onInteractBlock($this->owningPlayer, $blockReplace, $blockClicked, Facing::UP, $clickVector)->equals(ItemUseResult::SUCCESS());
    }
}