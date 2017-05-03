<?php
namespace Kimerikal\UtilBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class AjaxSelectType extends AbstractType {

    protected $router;

    public function __construct($router) {
        $this->router = $router;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options) {
        $builder->setAttribute('attr', array_merge($options['attr'], array('class' => 'form-control ajax-select', 'data-ajax-url' => $this->router->generate($options['route']))));
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options) {
        $view->vars['attr'] = $form->getConfig()->getAttribute('attr');
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver) {
        $resolver->setRequired(array('route'));
        $resolver->setDefaults(array('choices' => array(), 'choices_as_value' => true));
    }

    public function getParent() {
        return ChoiceType::class;
    }

    public function getName() {
        return 'ajax_select';
    }

}
