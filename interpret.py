#  interpret of IPPcode20 from XML file
#  author : Vojtech Coupek - xcoupe01

import sys
import re

# --- variables and constants ---
# exit codes
ERR_OK = 0  # < successful
ERR_PARAM = 10  # < missing parameter, bad combinations of params
ERR_IN_FILES = 11  # < cannot open input file
ERR_OUT_FILES = 12  # < cannot open output file
ERR_FORMAT_XML = 31  # < bad XML format on input
ERR_STRUCT_XML = 32  # < unexpected XML structure
ERR_SEMFAULT = 52  # < semantical fault in IPPcode20
ERR_BADTYPE_OP = 53  # < bad types of operands
ERR_UNDEF_VAR = 54  # < undefined variable access
ERR_NOTDEF_FR = 55  # < frame not defined
ERR_NOVAL_VAR = 56  # < missing value
ERR_BADVAL_OP = 57  # < bad value of operand
ERR_STRFAULT = 58  # < restricted string operations
ERR_INTERNAL = 99  # < internal fault

debug = True  # < print debug prints


# --- objects ---

class Frame:
    # Frame is object, that holds variable, its types and values
    # for each variable there is created item in every of three arrays
    # vars array contains name of variable
    # types array contains type of variable
    # values array contains value of variable
    # one variable is defined on the same index in all three fields
    defined = False
    vars = []
    types = []
    values = []

    def __init__(self, definition):
        self.defined = definition
        self.vars = []
        self.types = []
        self.values = []

    # Tries to create variable of given name
    # @err when frame is not defined or when variable is being redefined
    # @param var_name is name of variable to be created
    def createVar(self, var_name):
        if self.defined:
            try:
                self.vars[self.vars.index(var_name)] = [var_name + '-redefined']
                d_print("FRAME createVar - error variable " + var_name + " redefinition")
                exit(ERR_SEMFAULT)
            except ValueError:
                self.vars.append(var_name)
                self.types.append('')
                self.values.append('')
        else:
            d_print("FRAME insertVar - error accessing undefined frame")
            exit(ERR_NOTDEF_FR)

    # Sets variable type and value based on variable name
    # @err when frame is not defined or variable is undefined
    # @param var_name is name of variable to be updated
    # @param var_type is type that the variable is updated to
    # @param var_value is value that the variable is updated to
    def setVar(self, var_name, var_type, var_value):
        if self.defined:
            try:
                self.types[self.vars.index(var_name)] = var_type
                self.values[self.vars.index(var_name)] = var_value
            except IndexError:
                d_print("FRAME setVar - error variable " + var_name + " not defined")
                exit(ERR_UNDEF_VAR)
        else:
            d_print("FRAME setVar - error accessing undefined frame")
            exit(ERR_NOTDEF_FR)

    # returns type of given variable
    # @err when frame is not defined or variable is not defined
    # @param var_name is variable which type we want to know
    # @return variable type ('int', 'string' ect.) if successful
    def getVarType(self, var_name):
        if self.defined:
            try:
                return self.types[self.vars.index(var_name)]
            except IndexError:
                d_print("FRAME getVarType - error variable " + var_name + " not defined")
                exit(ERR_UNDEF_VAR)
        else:
            d_print("FRAME getVarType - error accessing undefined frame")
            exit(ERR_NOTDEF_FR)

    # returns value of given variable
    # @err when frame is not defined or variable is not defined
    # @param var_name is variable which value we want to know
    # @return variable value if successful
    def getVarVal(self, var_name):
        if self.defined:
            try:
                return self.values[self.vars.index(var_name)]
            except IndexError:
                d_print("FRAME setVar - error variable " + var_name + " not defined")
                exit(ERR_UNDEF_VAR)
        else:
            d_print("FRAME getVarVal - error accessing undefined frame")
            exit(ERR_NOTDEF_FR)

    # returns type of given variable if it have specific value
    # @err when frame is not defined or variable is not defined or variable is not the wanted value
    # @param var_name is variable which type we want to know
    # @param var_type is the type that we expect
    # @return variable value if successful
    def getVarValByType(self, var_name, var_type):
        if self.defined:
            try:
                if self.types[self.vars.index(var_name)] == var_type:
                    return self.values[self.vars.index(var_name)]
                elif self.types[self.vars.index(var_name)] == '':
                    d_print("FRAME setVar - error variable " + var_name + " no value in variable")
                    exit(ERR_NOVAL_VAR)
                else:
                    d_print("FRAME setVar - error variable " + var_name + " wrong value type")
                    exit(ERR_BADTYPE_OP)
            except IndexError:
                d_print("FRAME setVar - error variable " + var_name + " not defined")
                exit(ERR_UNDEF_VAR)
        else:
            d_print("FRAME getVarValByType - error accessing undefined frame")
            exit(ERR_NOTDEF_FR)

    # debug print function, that prints all data stored in frame
    # you need to have debug = True to make it work
    def printAllFrame(self):
        d_print("FRAME printAllFrame start")
        for x in range(len(self.vars)):
            d_print("\titem [" + str(x) + "] name  [" + self.vars[x] + "]")
            d_print("\titem [" + str(x) + "] type  [" + self.types[x] + "]")
            d_print("\titem [" + str(x) + "] value [" + self.values[x] + "]")
        d_print("FRAME printAllFrame end")


class DataStorage:
    GlobalFrame = Frame(True)
    TemporaryFrame = Frame(False)
    LocalFrame = []
    numLF = -1

    def __init__(self):
        self.GlobalFrame = Frame(True)
        self.TemporaryFrame = Frame(False)
        self.LocalFrame = []
        self.numLF = 0

    def createTempFrame(self):
        self.TemporaryFrame = Frame(True)

    def pushLocFrame(self):
        self.LocalFrame.append(self.TemporaryFrame)
        self.numLF += 1
        self.TemporaryFrame = Frame(False)

    def popLocFrame(self):
        if self.numLF >= 0:
            self.TemporaryFrame = self.LocalFrame.pop(self.numLF)
            self.numLF -= 1
        else:
            d_print("DATAS popLocFrame - error no local frame to be popped")
            exit(ERR_NOTDEF_FR)

    
# --- functions ---

def d_print(message):
    if debug:
        print(message)


# --- main ---
# dealing with args
sys.argv.remove('interpret.py')
srcFile = ''
inFile = ''
if (len(sys.argv) == 1) & ((sys.argv[0] == "--help") | (sys.argv[0] == '-h')):
    print('\n'
          ' Interpret of XML representation of IPPcode20\n'
          ' Options: \n'
          ' "--help" or "-h    to print help info\n'
          ' "--source=[file]"  to set file that the XML will be loaded from    **\n'
          ' "--input=[file]"   to set file that the input will be loaded from  **\n'
          ' ** at least one of those (last two) must be set. The unset on will be read from STDIN\n')
else:
    for i in range(len(sys.argv)):
        if (sys.argv[i] == '--help') | (sys.argv[i] == '-h'):
            d_print('ARG - err bad args')
            exit(10)
        elif re.match(r'^--source=([\S]+)$', sys.argv[i]) is not None:
            srcFile = re.match(r'^--source=([\S]+)$', sys.argv[i])
            d_print('ARG - source file set to [' + srcFile[1] + ']')
        elif re.match(r'^--input=([\S]+)$', sys.argv[i]) is not None:
            inFile = re.match(r'^--input=([\S]+)$', sys.argv[i])
            d_print('ARG - input file set to [' + inFile[1] + ']')
        else:
            d_print("Unknown arg")
srcHandle = sys.stdin
inHandle = sys.stdin
if (srcFile == '') & (inFile == ''):
    d_print("ARG - err at least one of --source or --input must be set")
    exit(11)
elif srcFile == '':
    inHandle = open(inFile[1], 'r')
    d_print('ARG - source set to [STDIN]')
elif inFile == '':
    srcHandle = open(srcFile[1], 'r')
    d_print('ARG - input set to [STDIN]')
else:
    srcHandle = open(srcFile[1], 'r')
    inHandle = open(inFile[1], 'r')

# frame test bench
local_frame = Frame(True)
local_frame.printAllFrame()
local_frame.createVar('variable1')
local_frame.createVar('var2')
local_frame.setVar('variable1', "string", "hello world")
local_frame.setVar('var2', "int", "5")
print(local_frame.getVarVal('variable1'))
print(local_frame.getVarType('variable1'))
print(local_frame.getVarVal('variable1'))
print(local_frame.getVarValByType('variable1', 'string'))
local_frame.printAllFrame()
local_frame.createVar('variable1')

"""
# temp code to test input and source
d_print('READ - src file')

for line in srcHandle:
    print(line)

d_print('READ -in file')

for line in inHandle:
    print(line)
"""
# it works yay

srcHandle.close()
inHandle.close()
