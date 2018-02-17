<?php

namespace Kimerikal\UtilBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;


class JSONArrayType extends AbstractType {

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options) {
        $builder->setAttribute('attr', array_merge($options['attr'], array('class' => 'form-control')));
    }

    public function finishView(FormView $view, FormInterface $form, array $options) {
        parent::finishView($view, $form, $options);
        $data = $view->vars['data'];
        $format = isset($options['format']) ? $options['format'] : null;
        if (!empty($data) && is_array($data)) {
            if (!empty($format)) {
                $format = explode(':', $format);
            }

            $showData = array();
            foreach ($data as $d) {
                $str = '';
                if (count($format) > 0) {
                    foreach ($format as $f) {
                        if (isset($d[$f]))
                            $str .= ' ' . $d[$f];
                    }
                } else
                    $str = $d;

                $showData[] = $str;
            }

            $view->vars['showData'] = $showData;
        }
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
        $resolver->setDefaults(array('format' => null));
    }

    public function getParent() {
        return TextareaType::class;
    }

    public function getName() {
        return 'json_array';
    }

}
