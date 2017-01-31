<?php

namespace Synapse\Cmf\Bundle\Form\Type\Theme;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Synapse\Cmf\Framework\Engine\Resolver\VariationResolver;
use Synapse\Cmf\Framework\Theme\ComponentType\Model\ComponentTypeInterface;
use Synapse\Cmf\Framework\Theme\ContentType\Model\ContentTypeInterface;
use Synapse\Cmf\Framework\Theme\TemplateType\Model\TemplateTypeInterface;
use Synapse\Cmf\Framework\Theme\Theme\Model\ThemeInterface;
use Synapse\Cmf\Framework\Theme\Variation\Entity\Variation;
use Synapse\Cmf\Framework\Theme\Variation\Entity\VariationContext;
use Synapse\Cmf\Framework\Theme\Zone\Domain\Command\UpdateCommand;
use Synapse\Cmf\Framework\Theme\Zone\Domain\ZoneDomain;
use Synapse\Cmf\Framework\Theme\Zone\Model\ZoneInterface;

/**
 * Zone edition form type.
 */
class ZoneType extends AbstractType implements DataTransformerInterface
{
    /**
     * @var ZoneDomain
     */
    protected $zoneDomain;

    /**
     * @var VariationResolver
     */
    protected $variationResolver;

    /**
     * Construct.
     *
     * @param ZoneDomain        $zoneDomain
     * @param VariationResolver $variationResolver
     */
    public function __construct(ZoneDomain $zoneDomain, VariationResolver $variationResolver)
    {
        $this->zoneDomain = $zoneDomain;
        $this->variationResolver = $variationResolver;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setRequired('theme');
        $resolver->setAllowedTypes('theme', ThemeInterface::class);

        $resolver->setRequired('content_type');
        $resolver->setAllowedTypes('content_type', ContentTypeInterface::class);

        $resolver->setRequired('template_type');
        $resolver->setAllowedTypes('template_type', TemplateTypeInterface::class);

        $resolver->setDefaults(array(
            'cascade_validation' => false,
            'data_class' => UpdateCommand::class,
        ));
    }

    /**
     * @see DataTransformerInterface::transform()
     */
    public function transform($data)
    {
        if ($data instanceof UpdateAction) {
            return $data;
        }
        if ($data instanceof ZoneInterface) {
            return $this->zoneDomain->getAction('update', $data);
        }

        throw new TransformationFailedException(sprintf(
            'Zone edition type only supports zone or zone update action. "%s" given.',
            gettype($data)
        ));
    }

    /**
     * Page form prototype definition.
     *
     * @see FormInterface::buildForm()
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->addModelTransformer($this)

            ->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($builder, $options) {
                $form = $event->getForm();
                $zone = $event->getData();

                // Create a component form for each into given zone
                $form->add('components', CollectionType::class, array(
                    'auto_initialize' => false,
                    'allow_add' => false,
                    'allow_delete' => false,
                    'entry_type' => ComponentType::class,
                    'entry_options' => array(
                        'variation' => $this->variationResolver->resolve((new VariationContext())->denormalize(array(
                            'theme' => $options['theme'],
                            'content_type' => $options['content_type'],
                            'template_type' => $options['template_type'],
                            'zone_type' => $zone->getZoneType(),
                        ))),
                    ),
                ));
            })
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        // here data are not transformed, so form data is zone
        $zone = $form->getData();

        $view->vars['component_types'] = $zone->getZoneType()
            ->getAllowedComponentTypes()
                ->indexBy('id')
                ->column('name')
        ;
        $view->vars['zone_type_id'] = $zone->getZoneType()->getId();
        $view->vars['zone_name'] = $zone->getZoneType()->getName();
    }

    /**
     * @see DataTransformerInterface::reverseTransform()
     */
    public function reverseTransform($data)
    {
        $data->resolve();

        return $data->getZone();
    }
}
