<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use BackendBundle\Entity\Post;
use AppBundle\Form\PublicationType;

class PublicationController extends Controller {

    private $session;

    public function __construct() {
        $this->session = new Session();
    }

    public function indexAction(Request $request) {
        $em = $this->getDoctrine()->getManager();
        $user = $this->getUser();
        $publication = new Post();
        $form = $this->createForm(PublicationType::class, $publication);

        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                //upload image
                $file = $form['image']->getData();
                if (!empty($file) && $file != null) {
                    $ext = $file->guessExtension();
                    if ($ext == "jpg" || $ext == "jpeg" || $ext == "png" || $ext == "gif") {
                        $file_name = $user->getId() . "-" . time() . "." . $ext;
                        $file->move('uploads/publications/images', $file_name);
                        $publication->setImage($file_name);
                    } else {
                        $publication->setImage(null);
                    }
                } else {
                    $publication->setImage(null);
                }

                //upload document
                $doc = $form['document']->getData();
                if (!empty($doc) && $doc != null) {
                    $ext = $doc->guessExtension();
                    if ($ext == "txt" || $ext == "pdf" || $ext == "docx" || $ext == "docx") {
                        $doc_name = $user->getId() . "-" . time() . "." . $ext;
                        $doc->move('uploads/publications/documents', $doc_name);
                        $publication->setDocument($doc_name);
                    } else {
                        $publication->setDocument(null);
                    }
                } else {
                    $publication->setDocument(null);
                }
                $publication->setUser($user);
                $createdAt = new \DateTime("now");
                $publication->setCreatedAt($createdAt);

                $em->persist($publication);
                $flush = $em->flush();

                if ($flush == null) {
                    $status = "Publication successfully created";
                } else {
                    $status = "Error, Publication fail";
                }
            } else {
                $status = "Invalid, Publication not created";
            }

            $this->session->getFlashBag()->add("status", $status);
            return $this->redirectToRoute('home_publications');
        }
        
       $publications = $this->getPublications($request);

        return $this->render('AppBundle:Publication:home.html.twig', array(
                    'form' => $form->createView(),
                    'publication' => $publications
        ));
    }

    public function getPublications($request) {
        $em = $this->getDoctrine()->getManager();
        $user = $this->getUser();

        $publications_repo = $em->getRepository('BackendBundle:Post');
        $following_repo = $em->getRepository('BackendBundle:Following');

        /*
         * SELECT text FROM posts WHERE user_id = 5 OR 
         * user_id IN (SELECT followed FROM following WHERE user_id = 5);
         */

        $following = $following_repo->findBy(array('user' => $user));
        $following_array = array();
        foreach ($following as $follow) {
            $following_array[] = $follow->getFollowed();
        }

        $query = $publications_repo->createQueryBuilder('p')
                ->where('p.user_id = (:user_id)OR p.user_id IN (:following')
                ->setParameter('user_id', $user->getId())
                ->setParameter('following', $following_array)
                ->orderBy('p.id', 'DESC')
                ->getQuery();
     
        $paginator = $this->get('knp_paginator');
        $pagination = $paginator->paginate(
            $query, 
            $request->query->getInt('page', 1), 
            5
        );
        return $pagination;
    }

}
