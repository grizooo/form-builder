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

        // "Static" mode: options directly as fusion properties
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

        // Try to render itemRenderer (if someone overwrote it) …
        $renderedItems = $this->renderItems($items);

        foreach ($items as $index => $item) {
            $value = null;
            $label = null;

            // 1) prefer explicit rendered itemRenderer output, if present
            if (isset($renderedItems[$index]) && is_array($renderedItems[$index])) {
                $value = $renderedItems[$index]['value'] ?? null;
                $label = $renderedItems[$index]['label'] ?? null;
            }

            // 2) fallback: read via property paths
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

        // ContentGraph Node: use getProperty for "properties.*"
        if ($item instanceof Node) {
            // typical paths: "properties.value" / "properties.label"
            if (str_starts_with($path, 'properties.')) {
                $propertyName = substr($path, strlen('properties.'));
                return $item->getProperty($propertyName);
            }
        }

        // Generic fallback
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
