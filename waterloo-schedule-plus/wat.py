#!/usr/bin/python

import urllib
import string
import time
import datetime
import pickle

from BeautifulSoup import BeautifulSoup


DEFAULT_TERM = 1085
SUBJECT_LIST = []
TERM_LIST = []
DB_FILENAME = 'dbList'
WATERLOO_LISTING_PAGE = "http://www.adm.uwaterloo.ca/cgi-bin/cgiwrap/infocour/salook.pl"
WATERLOO_SEARCH_PAGE  = "http://www.adm.uwaterloo.ca/infocour/CIR/SA/under.html"


class Course():
    subject = ""
    catalogNum = 0
    units = 0.5
    title = ""
    modules = []

class Module():
    classNum = 0
    component = ""
    sec = ""
    campus = "UW"
    location = "U"
    associatedClass = ""
    relatedComponent1 = ""
    relatedComponent2 = ""
    enrollCap = 0
    enrollTotal = 0
    waitingCap = 0
    waitingTotal = 0
    dates = []
    building = ""
    instructor = ""

def getSubjectListing(subject):
    data = urllib.urlencode({'sess' : DEFAULT_TERM , 'subject' : subject, 'submit' : 'Search!'})

    f = urllib.urlopen(WATERLOO_LISTING_PAGE, data)
    s = f.read()
    f.close()

    soup = BeautifulSoup(s)
    return soup

def populateDatabase(SUBJECT_LIST, delay = 5):
    dbList = []
    for subject in SUBJECT_LIST:
        subjectListing = getSubjectListing(subject)
        if not("your query had no matches" in subjectListing.b.contents[0]):
            dbList.append(parseSubject(subject, getSubjectListing(subject)))
            print "Got course listing for " + subject
        else:
            print "Got course listing for " + subject + ", but it was empty"
    time.sleep(delay)
    print "Got all course listings!"
    return dbList

def parseSubject(subject, subjectPage):
    courseList = []
    for tr in subjectPage.table:
        try:
            if string.strip(tr.td.contents[0]) == subject:
                course = Course()
                course.subject = subject
                course.catalogNum = string.strip(tr.contents[2].contents[0])
                course.units = string.strip(tr.contents[4].contents[0])
                course.title = string.strip(tr.contents[6].contents[0])
                moduleTable = tr.nextSibling.nextSibling.table
                for i in range((len(moduleTable.contents) - 1) / 2):
                    module = Module()
                    entry = moduleTable.contents[3 + (2 * i)]
                    module.classNum = string.strip(entry.contents[0].contents[0])           
                    module.component = string.strip(entry.contents[1].contents[0])
                    module.location = string.strip(entry.contents[2].contents[0])
                    module.associatedClass = string.strip(entry.contents[3].contents[0])
                    module.relatedComponent1 = string.strip(entry.contents[4].contents[0])
                    module.relatedComponent2 = string.strip(entry.contents[5].contents[0])
                    module.enrollCap = string.strip(entry.contents[6].contents[0])
                    module.enrollTotal = string.strip(entry.contents[7].contents[0])
                    module.waitingCap = string.strip(entry.contents[8].contents[0])
                    module.waitingTotal = string.strip(entry.contents[9].contents[0])
                    # TODO : do actual datetime parsing here
                    module.dates = string.strip(entry.contents[10].conents[0])
                    # module.building = string.strip(entry.contents[11].contents[0])
                    # module.instructor = string.strip(entry.contents[12].contents[0])
                courseList.append(course)
        except AttributeError:
            # Oh well, I guess it was a NavigableString.
            pass
        except IndexError:
            # Oh well, I guess it was empty.
            pass
        except TypeError:
            # God I hate HTML
            pass
    return courseList

# =============================================================================

f = urllib.urlopen(WATERLOO_SEARCH_PAGE)
s = f.read()
f.close()
soup = BeautifulSoup(s)

SUBJECT_LIST = [string.strip(x.contents[0]) for x in soup.findAll('option') if x.parent.attrs[0][1] == 'subject']
TERM_LIST = [string.strip(x.contents[0]) for x in soup.findAll('option') if x.parent.attrs[0][1] == 'sess']

SUBJECT_LIST = SUBJECT_LIST[:4]

print "Getting ready to parse " + str(len(SUBJECT_LIST)) + " subject listings..."

dbList = populateDatabase(SUBJECT_LIST, 1)

print "Parsed " + str(len(dbList)) + " listings with courses."

# Pickle it up
f = file(DB_FILENAME, 'w')
pickle.dump(dbList, f)
f.close()

print "Done!"
