/** ===========================================================
 * @file
 *
 * This file is a part of digiKam project
 * <a href="http://www.digikam.org">http://www.digikam.org</a>
 *
 * @date   2010-06-16
 * @brief  The Database class wraps the libface database
 *
 * @author Copyright (C) 2010 by Aditya Bhatt
 *         <a href="mailto:adityabhatt1991 at gmail dot com">adityabhatt1991 at gmail dot com</a>
 *
 * This program is free software; you can redistribute it
 * and/or modify it under the terms of the GNU General
 * Public License as published by the Free Software Foundation;
 * either version 2, or (at your option)
 * any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * ============================================================ */

// Qt includes

#include <stdio.h>

/*
#include <QApplication>
#include <QImage>
#include <QGraphicsView>
#include <QGraphicsScene>
#include <QGraphicsPixmapItem>
#include <QHBoxLayout>
#include <QLabel>
#include <QPixmap>
#include <QWidget>
*/
#include <QDebug>

// KDE includes


#include <kdebug.h>
#include <kstandarddirs.h>

// libkface includes

#include "libkface/database.h"
#include "libkface/face.h"
#include "libkface/faceitem.h"

using namespace KFaceIface;

void detectFaces(Database* d, const QString& file)
{
    qDebug() << "Loading" << file;
    QImage img(file);
    qDebug() << "Detecting";
	QList<Face> result = d->detectFaces(img);//QString::fromLocal8Bit(argv[1]));
    kDebug() << "Detected";

    if (result.isEmpty())
    {
        qDebug() << "No faces found";
        return;
    }
   

    qDebug() << "Recognising faces";
    QList<double> closeness = d->recognizeFaces(result);


	for(int i = 0 ; i <result.size(); ++i) {
		Face f = result[i];
		// Suggest name
		QRect r = f.toRect();
        qDebug() << "Detected face:" << r << "with id" << f.id();
		qDebug() << "Name might be" << f.name() << "with closeness" << closeness;

		printf("%d,%d %dx%d\n",f.id(), r.x(), r.y(), r.width(), r.height());
    }

    
/*
    QWidget* mainWidget = new QWidget;
    mainWidget->setWindowTitle(file);
    QHBoxLayout* layout = new QHBoxLayout(mainWidget);
    QLabel* fullImage   = new QLabel;
    fullImage->setPixmap(QPixmap::fromImage(img.scaled(250, 250, Qt::KeepAspectRatio)));
    layout->addWidget(fullImage);

    foreach (const Face& f, result)
    {
        QLabel* label = new QLabel;
        label->setScaledContents(false);
        QImage part   = img.copy(f.toRect());
        label->setPixmap(QPixmap::fromImage(part.scaled(200, 200, Qt::KeepAspectRatio)));
        layout->addWidget(label);
    }

    mainWidget->show();
    qApp->processEvents(); // dirty hack
	*/
}

int main(int argc, char** argv)
{
    if (argc < 2)
    {
        qDebug() << "Bad Args!!!\nUsage: " << argv[0] << " <image1> <image2> ...";
        return 0;
    }

    // Make a new instance of Database and then detect faces from the image
	Database* d = new Database(Database::InitAll, KStandardDirs::locateLocal("data", "libkface/database/", true));
	
    for (int i=1; i<argc; i++)
    {
		qDebug() << argv[i];
        detectFaces(d, QString::fromLocal8Bit(argv[i]));
    }
    //app.exec();
	
    return 0;
}
