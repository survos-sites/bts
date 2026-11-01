<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Amst;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

final class AmstCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Amst::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setSearchFields(['code', 'vondstnummer', 'objectBacking']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('code', 'code.label')->setFormTypeOption('disabled', Crud::PAGE_EDIT === $pageName);
        yield TextField::new('vondstnummer', 'vondstnummer.label');
        yield TextField::new('object', 'object.label');
        yield TextField::new('subcategorie', 'subcategorie.label');
        yield TextField::new('projectCode', 'projectCode.label');
        yield TextField::new('categorie', 'categorie.label');
        yield IntegerField::new('website')->hideOnIndex();
    }
}
