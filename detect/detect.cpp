/** ===========================================================
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

#include <stdio.h>

// Qt includes
#include <QFile>
#include <QDebug>
#include <QDataStream>

// KDE includes
#include <kdebug.h>
#include <kstandarddirs.h>


// libkface includes

#include "libkface/database.h"
#include <libkface/kfaceutils.h>

namespace libface
{
    class Face;
}

using namespace KFaceIface;

QList<Face> detectFaces(Database* d, const QString& file)
{
	qDebug() << "Loading" << file;
	QImage img(file);
	qDebug() << "Detecting";
	QList<Face> result = d->detectFaces(img);
	kDebug() << "Detected";

	if (result.isEmpty())
	{
		qDebug() << "No faces found";
		return result;
	}
	for(int i = 0 ; i <result.size(); ++i) {
		qDebug() << "detected face #" << result[i].id();
	}

	qDebug() << "Recognising faces";
	QList<double> closeness = d->recognizeFaces(result);

	for(int i = 0 ; i <result.size(); ++i) {
		Face f = result[i];
		// Suggest name
		QRect r = f.toRect();
		qDebug() << "Recognised face:" << r << "with id" << f.id();
		qDebug() << "Name might be" << f.name() << "with closeness" << closeness[i];

		printf("\n%d: %d,%d %dx%d\n", i, r.x(), r.y(), r.width(), r.height());
	}

	return result;
}

int main(int argc, char** argv) {

	if (argc < 2) {
		printf("Bad Args!\nUsage: %s [--face-1=\"name\"]... <image>", argv[0]);
		return 2;
	}

	// Make a new instance of Database and then detect faces from the image
	Database* d = new Database(Database::InitAll, KStandardDirs::locateLocal("data", "libkface/database/", true));

	//d->setDetectionAccuracy(0.1);
	//d->setDetectionSpecificity(0.1);

	QList<Face> faces = detectFaces(d, QString::fromLocal8Bit(argv[ argc -1 ]));
	QList<Face> trainingFaces;

	if(faces.size() < 1) {
		printf("Faces not found");
		return 1;
	}

	for (int i=1; i<argc; i++)
	{
		int facenum = -1;
		char name[254] = "";
		int matches = 0;
		matches = sscanf(argv[i], "--face-%d=%254c", &facenum, name);
		if(matches == 2) {
			if( facenum < 0 || facenum > faces.size()-1) {
				qDebug() << "No face in position" << facenum;
				continue;
			}

			faces[facenum].setName(QString::fromLocal8Bit(name));
			qDebug() << "Setting face in position" << facenum << "#" << faces[facenum].id() <<  "to name" << faces[facenum].name();
			trainingFaces.append(faces[facenum]);
		}
	}

	if(trainingFaces.size() >= 1) {
		qDebug() << "Training with" << trainingFaces.size() << "face(s)";
		if(d->updateFaces(trainingFaces)) {
			qDebug() << "Trained.";
			d->saveConfig();
		}
	}

	return 0;
}
	 