<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use BackendBundle\Entity\User;
use AppBundle\Form\RegisterType;
use AppBundle\Form\UserType;
use Symfony\Component\HttpFoundation\Session\Session;

class UserController extends Controller {

    private $session;

    public function __construct() {
        $this->session = new Session();
    }

    public function loginAction(Request $request) {
        /* Comprueba si hay una secion de usuario abierta, lo redirige a home */
        if (is_object($this->getUser())) {
            return $this->redirect('home');
        }
        $authenticationUtils = $this->get('security.authentication_utils'); // authenticacion de symfony
        $error = $authenticationUtils->getLastAuthenticationError(); // error al autenticacion
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('AppBundle:User:login.html.twig', array(
                    "last_username" => $lastUsername,
                    "error" => $error
        ));
    }

    public function registerAction(Request $request) {
        /* Comprueba si hay una secion de usuario abierta, lo redirige a home */
        if (is_object($this->getUser())) {
            return $this->redirect('home');
        }
        $user = new User();
        $form = $this->createForm(RegisterType::class, $user);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $em = $this->getDoctrine()->getManager();
                //$user_repo = $em->getRepository('BackendBundle:User');
                //
                //Comprovamos que el usuario no exista
                $query = $em->createQuery('SELECT u FROM BackendBundle:User u WHERE u.email = :email OR u.nick = :nick')
                        ->setParameter('email', $form->get("email")->getData())
                        ->setParameter('nick', $form->get("nick")->getData());
                $user_isset = $query->getResult();
                if (count($user_isset) == 0) {
                    $factory = $this->get("security.encoder_factory");
                    $encoder = $factory->getEncoder($user);

                    $password = $encoder->encodePassword($form->get("password")->getData(), $user->getSalt());

                    //Setear valores del Usuario
                    $user->setPassword($password);
                    $user->setRole("Role_User");
                    $user->setImage(null);
                    $em->persist($user);
                    $flush = $em->flush();

                    if ($flush == null) {
                        $status = "Te has registrado correctamente";

                        $this->session->getFlashBag()->add("status", $status);
                        return $this->redirect("login");
                    } else {
                        $status = "No te has registrado Correctamente!!!";
                    }
                } else {
                    $status = "El Usuario ya Existe!!";
                }
            } else {
                $status = "No te has registrado Correctamente!!!";
            }
            $this->session->getFlashBag()->add("status", $status);
        }
        return $this->render('AppBundle:User:register.html.twig', array(
                    "form" => $form->createView()
        ));
    }

    public function nickTestAction(Request $request) {
        //Comprobamos que el Nick no este en uso 
        $nick = $request->get("nick");
        $em = $this->getDoctrine()->getManager();
        $user_repo = $em->getRepository("BackendBlundle:User");
        $user_isset = $user_repo->findOneBy(array("nick" => $nick));

        $result = "used";
        if (count($user_isset) >= 1 && is_object($user_isset)) {
            $result = "used";
        } else {
            $result = "unused";
        }

        return new Response($result);
    }

    public function editUserAction(Request $request) {

        $user = $this->getUser();
        $user_image = $user->getImage();
        $form = $this->createForm(UserType::class, $user);

        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if ($form->isValid()) {

                $em = $this->getDoctrine()->getManager();

                $query = $em->createQuery('SELECT u FROM BackendBundle:User u WHERE u.email = :email OR u.nick = :nick')
                        ->setParameter('email', $form->get("email")->getData())
                        ->setParameter('nick', $form->get("nick")->getData());

                $user_isset = $query->getResult();

                if (count($user_isset) == 0 || ($user->getEmail() == $user_isset[0]->getEmail() && $user->getNick() == $user_isset[0]->getNick())) {

                    //load image
                    $file = $form["image"]->getData();

                    if (!empty($file) && $file != null) {
                        $ext = $file->guessExtension();
                        if ($ext == 'jpg' || $ext == 'jpeg' || $ext == 'png' || $ext == 'gif') {
                            $file_name = $user->getId() . '-' . time() . '.' . $ext;
                            $file->move("uploads/users", $file_name);

                            $user->setImage("uploads/users/" . $file_name);
                        }
                    } else {
                        $user->setImage($user_image);
                    }
                    $em->persist($user);
                    $flush = $em->flush();

                    if ($flush == null) {
                        $status = "Los cambios se han guardado correctamente";
                    } else {
                        $status = "Ha ocurrido un error al guardar los cambios";
                    }
                } else {
                    $status = "El Usuario ya Existe!!";
                }
            } else {
                $status = "No se han actualizado los datos Correctamente!!!";
            }
            $this->session->getFlashBag()->add("status", $status);
            return $this->redirect("my-data");
        }

        return $this->render('AppBundle:User:edit_user.html.twig', array(
                    "form" => $form->createView()
        ));
    }

    /** Mostrar Personas */
    public function usersAction(Request $request) {
        $em = $this->getDoctrine()->getManager();

        $dql = "SELECT u FROM BackendBundle:User u ORDER BY u.name ASC";
        $query = $em->createQuery($dql);

        $paginator = $this->get('knp_paginator');
        $pagination = $paginator
            ->paginate
                (
                    $query, $request->query->getInt('page', 1), 5
                );

        return $this->render('AppBundle:User:users.html.twig', array(
            'pagination' => $pagination
        ));
    }
    
    public function searchAction(Request $request) {
        $em = $this->getDoctrine()->getManager();
        
        $search = $request->get("search", null);
        
        if($search == null)
        {
            return $this->redirect($this->generateURL('home_publications'));
        } 
        
        $dql = "SELECT u FROM BackendBundle:User u WHERE u.name LIKE :search OR u.lastname LIKE :search OR u.nick LIKE :search ORDER BY u.name ASC ";
        $query = $em->createQuery($dql)->setParameter('search', "%$search%");

        $paginator = $this->get('knp_paginator');
        $pagination = $paginator
            ->paginate
                (
                    $query, $request->query->getInt('page', 1), 5
                );

        return $this->render('AppBundle:User:users.html.twig', array(
            'pagination' => $pagination
        ));
    }

}
