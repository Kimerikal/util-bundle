<?php

namespace Kimerikal\UtilBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;

class JSONArrayType extends AbstractType {

    protected $em;

    public function __construct(EntityManager $em) {
        $this->em = $em;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options) {
        $builder->setAttribute('attr', array_merge($options['attr'], array('class' => 'form-control')));
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $object = $event->getForm()->getParent()->getData();
            //$data = $event->getParent()->getData();
            if (is_object($object)) {
                $fieldName = $event->getForm()->getName();
                $method = 'formatNew' . ucfirst($fieldName);
                //$getMethod = 'set' . ucfirst($fieldName);
                $origData = array();
                $newData = $event->getData();
                $oldData = $this->em->getUnitOfWork()->getOriginalEntityData($object);
                if (!empty($oldData))
                    $origData = $oldData[$fieldName];

                if (method_exists($object, $method)) {
                    $newdata = call_user_func_array(array($object, $method), array($newData));
                }

                \array_push($origData, $newdata);

                $event->setData($origData);
            }
        });
    }

    public function finishView(FormView $view, FormInterface $form, array $options) {
        parent::finishView($view, $form, $options);
        $view->vars['inputType'] = isset($options['inputType']) ? $options['inputType'] : 'textarea';

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
        $resolver->setDefaults(array('format' => null, 'inputType' => null));
    }

    public function getParent() {
        return TextareaType::class;
    }

    public function getName() {
        return 'json_array';
    }

}
