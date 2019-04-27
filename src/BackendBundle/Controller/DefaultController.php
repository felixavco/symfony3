<?php

namespace BackendBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction()
    {
        $em = $this->getDoctrine()->getManager();
        $user_repo = $em->getRepository("BackendBundle:User");
        $user = $user_repo->find(3);
        echo "Bienvenido " .$user->getName()." ".$user->getLastname();
        echo "<br/>";
        echo "Su correo es: ".$user->getEmail();
//        var_dump($user);
        die();
        return $this->render('BackendBundle:Default:index.html.twig');
    }
}
