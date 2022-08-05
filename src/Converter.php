<?php

namespace Converter;

use ReflectionClass;
use ReflectionObject;
use ReflectionProperty;

class Converter
{
    /**
     * @var object
     */
    private $source;

    /**
     * @var ReflectionObject
     */
    private $sourceRef;

    /**
     * @var ReflectionClass
     */
    private $targetRef;

    /**
     * 目标字段 => 源字段
     * @var array<string, string>
     */
    private $mapping = [];

    /**
     * 回调方法
     * @var array
     */
    private $callback = [];

    /**
     * Convertor constructor.
     */
    private function __construct() { }

    /**
     * 建立转换器
     *
     * @param object $source
     * @param string $target
     *
     * @return Converter
     * @throws ConvertException
     */
    public static function builder(object $source, string $target) : Converter
    {
        if (!class_exists($target))
            throw new ConvertException("类{$target}不存在");
        $convertor = new self();
        $convertor->source = $source;
        try {
            $convertor->sourceRef = new ReflectionObject($source);
            $convertor->targetRef = new ReflectionClass($target);
            return $convertor;
        } catch (\ReflectionException $e) {
            throw new ConvertException('建立失败');
        }
    }

    /**
     * 转换
     *
     * @return mixed
     * @throws ConvertException
     */
    public function convert()
    {
        try {
            $target = $this->targetRef->newInstanceWithoutConstructor();
            foreach ($this->targetRef->getProperties() as $targetPropertyRef) {
                $name = $targetPropertyRef->getName();
                $sourcePropertyRef = $this->getSourcePropertyReflection($name);
                if (empty($sourcePropertyRef)) continue;
                $sourcePropertyRef->setAccessible(true);
                $value = $this->callbackProcessing($name, $sourcePropertyRef->getValue($this->source));
                // 设置目标属性值
                $targetPropertyRef->setAccessible(true);
                $targetPropertyRef->setValue($target, $value);
            }
            return $target;
        } catch (\ReflectionException $e) {
            throw new ConvertException('转换失败');
        }
    }

    /**
     * 获取源属性反射对象
     *
     * @param string $propertyName
     *
     * @return ReflectionProperty|null
     * @throws ConvertException
     */
    private function getSourcePropertyReflection(string $propertyName) : ?ReflectionProperty
    {
        try {
            if (!$this->sourceRef->hasProperty($propertyName) && isset($this->mapping[$propertyName]))
                $propertyName = $this->mapping[$propertyName];
            if ($this->sourceRef->hasProperty($propertyName))
                return $this->sourceRef->getProperty($propertyName);
            return null;
        } catch (\ReflectionException $e) {
            throw new ConvertException("获取源属性失败");
        }
    }

    /**
     * 回调方法处理
     *
     * @param string $name
     * @param        $value
     *
     * @return mixed
     */
    private function callbackProcessing(string $name, $value)
    {
        if (empty($this->callback[$name]))
            return $value;
        foreach ($this->callback[$name] as $callback) {
            $value = $callback($value);
        }
        return $value;
    }

    /**
     * 目标属性映射定义
     *
     * @param string          $target
     * @param string|callable $source
     *
     * @return $this
     * @throws ConvertException
     */
    public function mapping(string $target, $source) : self
    {
        if (!$this->targetRef->hasProperty($target))
            throw new ConvertException("不存在目标属性{$target}");
        if (is_string($source)) {
            if (!$this->sourceRef->hasProperty($source))
                throw new ConvertException("不存在源属性{$source}");
            $this->mapping[$target] = $source;
            return $this;
        }
        if (!is_callable($source)) {
            throw new ConvertException("第二个参数不是一个回调方法");
        }
        empty($this->callback[$target]) && $this->callback[$target] = [];
        $this->callback[$target][] = $source;
        return $this;
    }
}