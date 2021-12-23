<?php

namespace Kimerikal\UtilBundle\Form\Type;

use Kimerikal\UtilBundle\Entity\TimeUtil;
use Kimerikal\UtilBundle\Form\DataTransformer\EntityAjaxSelectTransformer;
use Kimerikal\UtilBundle\Model\AjaxSelect2;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
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
use Kimerikal\UtilBundle\Entity\ExceptionUtil;

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
        $route = $options['route'];
        $routeParams = [];
        $tmp = explode(':', $route);
        if (count($tmp) === 3) {
            $route = $tmp[0];
            $routeParams[$tmp[1]] = $tmp[2];
        }
        $builder->setAttribute('attr', array_merge($options['attr'], array('class' => 'form-control ajax-select', 'data-ajax-url' => $this->router->generate($route, $routeParams))));
        $builder->resetViewTransformers();
        $builder->addViewTransformer(new EntityAjaxSelectTransformer($this->em, $options['target_object']), true);
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
                $val = new \stdClass();
                $bdData = $this->em->getRepository($options['target_object'])->find($data);
                if ($bdData instanceof AjaxSelect2) {
                    $val->value = $bdData->getId();
                    $val->text = $bdData->getTitle();
                } else if ($bdData instanceof Select2FormField) {
                    /** @deprecated */
                    $val->value = $bdData->getId();
                    $val->text = $bdData->select2text();
                } else {
                    $val->value = $bdData->getId();
                    $val->text = $bdData->__toString();
                }
                $view->vars['curVal'] = $val;
            } catch (\Exception $e) {
                ExceptionUtil::logException($e, 'EntityAjaxSelectType::finishView');
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['attr'] = $form->getConfig()->getAttribute('attr');
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
        return 'entity';
    }

    public function getName()
    {
        return 'entity_ajax_select';
    }

}
