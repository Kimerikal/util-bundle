<?php

namespace Kimerikal\UtilBundle\Form\Type;

use Kimerikal\UtilBundle\Entity\TimeUtil;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Doctrine\ORM\EntityManager;
use Kimerikal\UtilBundle\Model\Select2FormField;

class EntityAjaxSelectType extends AbstractType
{

    protected $router;
    protected $em;

    public function __construct($router, EntityManager $em)
    {
        $this->router = $router;
        $this->em = $em;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->setAttribute('attr', array_merge($options['attr'], array('class' => 'form-control ajax-select', 'data-ajax-url' => $this->router->generate($options['route']))));
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($options) {
            if (empty($event->getData()))
                return;

            $data = $event->getData();
            $choices = array();
            if(is_array($data)){
                foreach($data as $choice){
                    $choices[$choice] = $choice;
                }
            } else{
                $choices[$data] = (string) $data;
            }

            $event->getForm()->getParent()->add($event->getForm()->getName(), 'entity_ajax_select', array('class' => $options['target_object'], 'choices' => $choices, 'label' => $options['label'], 'attr' => $options['attr'], 'route' => $options['route']));

           /* $bdData = $this->em->getRepository($options['target_object'])->find($event->getData());
            if ($bdData) {
               // $event->setData($bdData);
                //$event->setData([$bdData->getId() => $bdData]);
                $event->getForm()->getParent()->add($event->getForm()->getName(), 'entity_ajax_select', array('class' => $options['target_object'], 'choices' => $choices, 'data' => $bdData->getId(), 'label' => $options['label'], 'attr' => $options['attr'], 'route' => $options['route']));
               // $event->getForm()->getParent()->add($event->getForm()->getName(), 'entity_ajax_select', array('class' => $options['target_object'], 'choice_value' => function($entity) use ($bdData) {return $bdData;}, 'choices' => [$bdData->getId() => $bdData], 'route' => $options['route']));
            }*/
        });
    }

    /**
     * {@inheritdoc}
     */
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        parent::finishView($view, $form, $options);
        $data = $view->vars['value'];
        $view->vars['curVal'] = null;
        if (!empty($data)) {
            try {
                $bdData = $this->em->getRepository($options['target_object'])->find($data);
                if ($data instanceof Select2FormField) {
                    $val = new \stdClass();
                    $val->value = $bdData->select2id();
                    $val->text = $bdData->select2text();

                    $view->vars['curVal'] = $val;
                }
            } catch (\Exception $e) {
                error_log($e->getMessage());
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['attr'] = $form->getConfig()->getAttribute('attr');
        /* if (!empty($view->vars['value']) && is_object($view->vars['value'])) {
             $view->vars['data'] = $view->vars['value'];
             $view->vars['value'] = $view->vars['value']->getId();
         }*/
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setRequired(array('route', 'target_object'));
        $resolver->setDefaults(array('choices' => array(), 'target_object' => null));
    }

    public function getParent()
    {
        return EntityType::class;
    }

    public function getName()
    {
        return 'entity_ajax_select';
    }

}
