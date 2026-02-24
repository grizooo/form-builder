<?php
namespace Neos\Form\Builder\Fusion;

use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\Fusion\FusionObjects\AbstractArrayFusionObject;
use Neos\Utility\ObjectAccess;

class SelectOptionCollectionImplementation extends AbstractArrayFusionObject
{
    protected $ignoreProperties = [
        'prependOptionLabel',
        'prependOptionValue',
        'items',
        'itemName',
        'itemRenderer',
        'valuePropertyPath',
        'labelPropertyPath',
    ];

    public function evaluate()
    {
        $items = $this->getItems();
        $options = [];

        if (!empty($prependLabel = $this->getPrependOptionLabel())) {
            $options[$this->getPrependOptionValue()] = $prependLabel;
        }

        if ($items === null) {
            foreach ($this->properties as $propertyName => $propertyValue) {
                if (in_array($propertyName, $this->ignoreProperties, true)) {
                    continue;
                }
                $options[$propertyName] = $propertyValue;
            }
            return $options;
        }

        // "Items" mode: build options from nodes/items
        $valuePath = $this->getValuePropertyPath();
        $labelPath = $this->getLabelPropertyPath();

        $renderedItems = $this->renderItems($items);

        foreach ($items as $index => $item) {
            $value = null;
            $label = null;

            if (isset($renderedItems[$index]) && is_array($renderedItems[$index])) {
                $value = $renderedItems[$index]['value'] ?? null;
                $label = $renderedItems[$index]['label'] ?? null;
            }

            if ($value === null) {
                $value = $this->readByPath($item, $valuePath);
            }
            if ($label === null) {
                $label = $this->readByPath($item, $labelPath);
            }

            if ($value === null || $value === '') {
                continue;
            }

            if ($label === null || $label === '') {
                $label = (string)$value;
            }

            $options[(string)$value] = (string)$label;
        }

        return $options;
    }

    private function readByPath($item, string $path)
    {
        if ($path === '') {
            return null;
        }

        if ($item instanceof Node) {
            if (str_starts_with($path, 'properties.')) {
                $propertyName = substr($path, strlen('properties.'));
                return $item->getProperty($propertyName);
            }
        }

        try {
            return ObjectAccess::getPropertyPath($item, $path);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function getItems(): ?iterable
    {
        return $this->fusionValue('items');
    }

    private function getItemName(): string
    {
        return $this->fusionValue('itemName');
    }

    private function getPrependOptionLabel(): string
    {
        return $this->fusionValue('prependOptionLabel') ?? '';
    }

    private function getPrependOptionValue(): string
    {
        return $this->fusionValue('prependOptionValue') ?? '';
    }

    private function getValuePropertyPath(): string
    {
        return (string)($this->fusionValue('valuePropertyPath') ?? '');
    }

    private function getLabelPropertyPath(): string
    {
        return (string)($this->fusionValue('labelPropertyPath') ?? '');
    }

    private function renderItems(iterable $items): array
    {
        $itemName = $this->getItemName();
        $itemRenderPath = $this->path . '/itemRenderer';

        $result = [];
        if ($this->runtime->canRender($itemRenderPath) === true) {
            foreach ($items as $item) {
                $context = $this->runtime->getCurrentContext();
                $context[$itemName] = $item;
                $this->runtime->pushContextArray($context);
                $result[] = $this->runtime->render($itemRenderPath);
                $this->runtime->popContext();
            }
        }
        return $result;
    }
}
