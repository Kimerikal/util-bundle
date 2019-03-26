<?php

namespace Kimerikal\UtilBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;

class TreeSelectType extends AbstractType {

    protected $em;

    public function __construct(EntityManager $em) {
        $this->em = $em;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options) {
        $builder->setAttribute('attr', array_merge($options['attr'], array('class' => 'form-control tree-select')));

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function(FormEvent $event) use ($options) {
            $data = $event->getData();
            if ($data) {
                try {
                    $bdData = $this->em->getRepository($options['target_object'])->find($data);
                } catch (\Exception $e) {
                    $msg = $e->getMessage();
                }
            }
        });
    }

    public function finishView(FormView $view, FormInterface $form, array $options) {
        parent::finishView($view, $form, $options);
        /*$data = $view->vars['data'];
        $id = $view->vars['id'];
        $id2 = $form->get('id')->getData();*/
        $tree = $this->em->getRepository($options['target_object'])->map();
        $view->vars['dataCat'] = $this->treeToJson($tree);
        /* if (!empty($data)) {
          try {
          $bdData = $this->em->getRepository($options['target_object'])->find($data);
          if ($bdData && $bdData instanceof Select2FormField) {
          $val = new \stdClass();
          $val->value = $bdData->select2id();
          $val->text = $bdData->select2text();

          $view->vars['dataCat'] = $val;
          }
          } catch (\Exception $e) {
          $msg = $e->getMessage();
          }
          } */
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
        $resolver->setRequired(array('target_object'));
        $resolver->setDefaults(array('choices' => array(), 'choices_as_value' => true, 'target_object' => null));
    }

    private function addToObj($selected, &$obj, $targetObject) {
        if (!empty($selected)) {
            $parents = explode("|", $selected);
            foreach ($obj->getCategories() as $category) {
                $obj->removeCategory($category);
            }

            foreach ($parents as $c) {
                $cat = $this->em->getRepository($targetObject)->find($c); //'KBlogBundle:BlogCategory'
                if ($cat) {
                    $obj->addCategory($cat);
                }
            }
        }
    }

    private function treeToJson($tree, $object = null, $openAllNodes = false, $hrefPattern = array(), $isSon = false) {
        $data = array();
        $addHref = \count($hrefPattern) > 0 && isset($hrefPattern['route']) && isset($hrefPattern['params']);
        foreach ($tree as $node) {
            $arr = array('id' => $node->getId(), 'text' => $node->getName());
            if ((!is_null($object) && $object->hasCategory($node->getId()))) {
                $arr['state'] = array('selected' => true);
            }

            if ($openAllNodes) {
                if (isset($arr['state']) && is_array($arr['state']))
                    $arr['state']['opened'] = true;
                else
                    $arr['state'] = array('opened' => true);
            }

            if ($addHref) {
                $params = array();
                foreach ($hrefPattern['params'] as $key => $method) {
                    $params[$key] = \call_user_func(array($node, $method));
                }

                $arr['a_attr'] = array('href' => $this->generateUrl($hrefPattern['route'], $params));
            }

            if (count($node->getChildren()) > 0) {
                $arr['children'] = $this->treeToJson($node->getChildren(), $object, $openAllNodes, $addHref, true);
            }

            $data[] = $arr;
        }

        if (!$isSon)
            return \json_encode($data, \JSON_HEX_QUOT);
        else
            return $data;
    }

    public function getName() {
        return 'tree_select';
    }

}
