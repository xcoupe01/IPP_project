#  interpret of IPPcode20 from XML file
#  author : Vojtech Coupek - xcoupe01

import argparse
import re
import sys
import xml.etree.ElementTree as xml

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

rules = [
    # base functions
    ['MOVE', 'var', 'symb'],
    ['CREATEFRAME'],
    ['PUSHFRAME'],
    ['POPFRAME'],
    ['DEFVAR'],
    ['CALL'],
    ['RETURN'],
    # stack operations
    ['PUSHS', 'symb'],
    ['POPS', 'var'],
    # arithmetical operations, logical operation, conversions
    ['ADD', 'var', 'symb', 'symb'],
    ['SUB', 'var', 'symb', 'symb'],
    ['MUL', 'var', 'symb', 'symb'],
    ['IDIV', 'var', 'symb', 'symb'],
    ['LG', 'var', 'symb', 'symb'],
    ['GT', 'var', 'symb', 'symb'],
    ['EQ', 'var', 'symb', 'symb'],
    ['AND', 'var', 'symb', 'symb'],
    ['OR', 'var', 'symb', 'symb'],
    ['NOT', 'var', 'symb'],
    ['INT2CHAR', 'var', 'symb'],
    ['STRI2INT', 'var', 'symb', 'symb'],
    # input output operations
    ['READ', 'var', 'type'],
    ['WRITE', 'symb'],
    # string operations
    ['CONCAT', 'var', 'symb', 'symb'],
    ['STRLEN', 'var', 'symb'],
    ['GETCHAR', 'var', 'symb', 'symb'],
    ['SETCHAR', 'var', 'symb', 'symb'],
    # type operations
    ['TYPE', 'var', 'symb'],
    # jump operations
    ['LABEL', 'label'],
    ['JUMP', 'label'],
    ['JUMPIFEQ', 'label', 'symb', 'symb'],
    ['JUMPIFNEQ', 'label', 'symb', 'symb'],
    ['EXIT', 'symb'],
    # debuging operations
    ['DPRINT', 'symb'],
    ['BREAK']
]


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

    # Says if the frame is defined
    # @return true if defined, false otherwise
    def isDefined(self):
        return self.defined

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
                self.types.append('undef')
                self.values.append('undef')
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


class VariableStorage:
    GlobalFrame = Frame(True)
    TemporaryFrame = Frame(False)
    LocalFrame = []
    numLF = -1

    def __init__(self):
        self.GlobalFrame = Frame(True)
        self.TemporaryFrame = Frame(False)
        self.LocalFrame = []
        self.numLF = -1

    # creates new temporary frame. If there was already one, its overwrote
    def createTempFrame(self):
        self.TemporaryFrame = Frame(True)

    # pushes temporary frame to stack of local frames and sets it
    # to currently used local frame
    # @err when the temporary frame is not defined
    def pushLocFrame(self):
        if self.TemporaryFrame.isDefined():
            self.LocalFrame.append(self.TemporaryFrame)
            self.numLF += 1
            self.TemporaryFrame = Frame(False)
        else:
            d_print("DATAS pushLocFrame - error temporary frame not defined")
            exit(ERR_NOTDEF_FR)

    # pops local frame from the local frames stack to temporary frame
    # this overwrites the temporary frame
    # @err when there is no local frame in stack
    def popLocFrame(self):
        if self.numLF >= 0:
            self.TemporaryFrame = self.LocalFrame.pop(self.numLF)
            self.numLF -= 1
        else:
            d_print("DATAS popLocFrame - error no local frame to be popped")
            exit(ERR_NOTDEF_FR)

    # creates var in variable storage based on the variable string from IPPcode20
    # @err when the variable string is bad or when trying to redefine existing variable
    # @param var_str is the variable defining string from IPPcode20
    def createVar(self, var_str):
        checkNameVar(var_str)
        var = re.match(r'^(\wF)@([\w_\-$&%*!?]+)$', var_str)
        if var[1] == "GF":
            self.GlobalFrame.createVar(var[2])
        elif var[1] == "LF":
            self.LocalFrame[self.numLF].createVar(var[2])
        elif var[1] == "TF":
            self.TemporaryFrame.createVar(var[2])

    # sets value and type to already created variable in variable storage
    # @err when variable string is bad or the variable is not defined
    # @param var_str is the variable defining string from IPPcode20
    # @param var_type is the type the variable will be set to
    # @param var_type is the value the variable will be set to
    def setVar(self, var_str, var_type, var_value):
        checkNameVar(var_str)
        checkValueByType(var_type, var_value)
        var = re.match(r'^(\wF)@([\w_\-$&%*!?]+)$', var_str)
        if var[1] == "GF":
            self.GlobalFrame.setVar(var[2], var_type, var_value)
        elif var[1] == "TF":
            self.LocalFrame[self.numLF].setVar(var[2], var_type, var_value)
        elif var[1] == "LF":
            self.TemporaryFrame.setVar(var[2], var_type, var_value)

    # returns type of given variable in IPPcode20 notation
    # @err when variable string is bad or the variable is not defined
    # @param var_str is the variable defining string from IPPcode20
    # @return type of given variable if successful
    def getVarType(self, var_str):
        checkNameVar(var_str)
        var = re.match(r'^(\wF)@([\w_\-$&%*!?]+)$', var_str)
        if var[1] == "GF":
            return self.GlobalFrame.getVarType(var[2])
        elif var[1] == "TF":
            return self.LocalFrame[self.numLF].getVarType(var[2])
        elif var[1] == "LF":
            return self.TemporaryFrame.getVarType(var[2])

    # returns value of given variable in IPPcode20 notation
    # @err when variable string is bad or the variable is not defined
    # @param var_str is the variable defining string from IPPcode20
    # @return value of given variable if successful
    def getVarVal(self, var_str):
        checkNameVar(var_str)
        var = re.match(r'^(\wF)@([\w_\-$&%*!?]+)$', var_str)
        if var[1] == "GF":
            return self.GlobalFrame.getVarVal(var[2])
        elif var[1] == "TF":
            return self.LocalFrame[self.numLF].getVarVal(var[2])
        elif var[1] == "LF":
            return self.TemporaryFrame.getVarVal(var[2])

    # returns value of given variable in IPPcode20 notation if it
    # matches expected type
    # @err when variable string is bad or the variable is not defined or its not the expected type
    # @param var_str is the variable defining string from IPPcode20
    # @param var_type is the type we expect
    def getVarValByType(self, var_str, var_type):
        checkNameVar(var_str)
        var = re.match(r'^(\wF)@([\w_\-$&%*!?]+)$', var_str)
        if var[1] == "GF":
            return self.GlobalFrame.getVarValByType(var[2], var_type)
        elif var[1] == "TF":
            return self.TemporaryFrame.getVarValByType(var[2], var_type)
        elif var[1] == "LF":
            return self.LocalFrame[self.numLF].getVarValByType(var[2], var_type)

    # debug print that prints whole content of global frame, temporary frame and
    # number of local frames in stack
    # you need debug = True to make it work
    def printStat(self):
        d_print("DATAS printStat start\nGlobal Frame:")
        self.GlobalFrame.printAllFrame()
        d_print("Temporary Frame:")
        self.TemporaryFrame.printAllFrame()
        d_print("Number of local frames :" + str(self.numLF + 1))


class StackStorage:
    # stored similarly as the variables above. The value and type is separated
    # to two arrays. One index to both of them presents one item
    # stackTop variable tells how many items are in the stack and also is index to top
    valueArray = []
    typeArray = []
    stackTop = -1

    def __init__(self):
        self.valueArray = []
        self.typeArray = []
        self.stackTop = -1

    # pushes item to stack
    # @param item_value is value of the item to be pushed
    # @param item_type is type of the item to be pushed
    def stackPush(self, item_value, item_type):
        checkValueByType(item_type, item_value)
        self.valueArray.append(item_value)
        self.typeArray.append(item_type)
        self.stackTop += 1

    # pops item out of the stack
    # @err when the stack is empty
    # @returns top item value if successful
    def stackPopValue(self):
        if self.stackTop >= 0:
            self.typeArray.pop()
            return self.valueArray.pop()
        else:
            d_print("STAKS stackPopValue - error nothing in stack to pop")
            exit(ERR_NOVAL_VAR)

    # returns type of the first item in the stack - not a pop !!
    # @err when the stack is empty
    # @return type of the top item
    def stackTopType(self):
        try:
            return self.typeArray[self.stackTop]
        except IndexError:
            d_print("STAKS stackTopVal - error nothing in the stack")
            exit(ERR_NOVAL_VAR)

    # returns top stack item value if it matches required type
    # @err when the stack is empty or when the item doesnt match the required type
    # @param item_type is the required item type
    # @return top item value if successful
    def stackPopValueByType(self, item_type):
        if self.stackTop >= 0:
            if self.typeArray[self.stackTop] == item_type:
                self.typeArray.pop()
                return self.valueArray.pop()
            else:
                d_print("STAKS stackPopValueByType - error stack top wrong value")
                exit(ERR_BADTYPE_OP)
        else:
            d_print("STAKS stackPopValueByType - error nothing in the stack")
            exit(ERR_NOVAL_VAR)


class LabelStorage:
    # stored similarly as variables. Label names and lines are stored separately
    # label names in array labelNames and label lines in labelLine. One label defines
    # same index to both fields
    labelNames = []
    labelLines = []

    def __init__(self):
        self.labelNames = []
        self.labelLines = []

    # adds a label to the list
    # @param label_name is name of the label
    # @param label_line is line of the label
    def addLabel(self, label_name, label_line):
        checkLabelName(label_name)
        if self.getLabelLine(label_name) == -1:
            self.labelNames.append(label_name)
            self.labelLines.append(label_line)
        else:
            d_print("LABLS addLabel - error label redefinition")
            exit(ERR_SEMFAULT)

    # returns line of a given label name
    # @err when label is not defined
    # @param label_name is label to be searched for
    # @return label line if successful
    def getLabelLine(self, label_name):
        checkLabelName(label_name)
        for y in range(len(self.labelNames)):
            if self.labelNames[y] == label_name:
                return self.labelLines[y]
        d_print("LABLS getLabelLine - error label not found")
        exit(ERR_SEMFAULT)
    # I need label finder, that scans the whole file first ------------------------------------------ TODO
    # and stores all labels in here.


class FileProcessor:
    srcFileHandle = sys.stdin
    inFileHandle = sys.stdin
    args = None
    code = []

    def __init__(self):
        self.srcFileHandle = sys.stdin
        self.inFileHandle = sys.stdin
        self.args = None
        self.code = []

    # goes through program arguments, saves program settings into args variable
    # and prepares source and input files
    # @err when uknown argument appears, when neither of source and input is set
    #      when help setting occurs with other arguments, when files cannot be opened
    def setHandles(self):
        # deal wit args
        parser = argparse.ArgumentParser(add_help=False)
        # basic arguments
        parser.add_argument('-h', '--h', dest='help', action='count')
        parser.add_argument('--source', default=None)
        parser.add_argument('--input', default=None)
        # additional arguments
        parser.add_argument('--stats', default=None)
        parser.add_argument('--insts', dest='insts', action='count')
        parser.add_argument('--vars', dest='vars', action='count')
        parser.error = arg_err
        self.args = parser.parse_args()
        if self.args.help is not None:
            if (self.args.help == 1) & (self.args.input is None) & (self.args.insts is None) & \
                    (self.args.source is None) & (self.args.stats is None) & (self.args.vars is None):
                print('\n'
                      ' Interpret of XML representation of IPPcode20\n'
                      ' Options: \n'
                      ' "--help" or "-h    to print help info\n'
                      ' "--source=[file]"  to set file that the XML will be loaded from    **\n'
                      ' "--input=[file]"   to set file that the input will be loaded from  **\n'
                      ' ** at least one of those (last two) must be set. The unset on will be read from STDIN\n')
                exit(ERR_OK)
            else:
                d_print("FILES setHandles - error help used with other arguments")
                exit(ERR_PARAM)
        else:
            if (self.args.input is None) & (self.args.source is None):
                d_print("FILES setHandles - error both source and input unset")
                exit(ERR_PARAM)
            else:
                if self.args.input is not None:
                    try:
                        self.inFileHandle = open(self.args.input, 'r', encoding='UTF-8')
                    except OSError:
                        d_print("FILES setHandles - error input file does not exist")
                        exit(ERR_IN_FILES)
                elif self.args.source is not None:

                    try:
                        self.srcFileHandle = open(self.args.source, 'r', encoding='UTF-8')
                    except OSError:
                        d_print("FILES setHandles - error source file does not exist")
                        exit(ERR_IN_FILES)

    # takes source file input and translates the xml format to array code
    # @err when bad xml structure appears
    # @return code when successful
    def xmlTranslate(self):
        xmlcode = None
        try:
            xmlcode = xml.parse(self.srcFileHandle).getroot()
        except:
            d_print("FILES xmlTranslate - error bad xml file")
            exit(ERR_STRUCT_XML)
        progheader = False
        for attribute, value in xmlcode.attrib.items():
            if (attribute == 'language') & (value == 'IPPcode20'):
                progheader = True
            elif (attribute != 'name') | (attribute != 'description'):
                d_print("FILES xmlTranslate - error bad program attributes")
                exit(ERR_STRUCT_XML)
        if not progheader:
            d_print("FILES xmlTranslate - error bad program attributes")
            exit(ERR_STRUCT_XML)
        ins_order = 0
        for instruction in xmlcode:
            ins_order += 1
            if not ((instruction.attrib['order'] == str(ins_order)) & isOpcode(instruction.attrib['opcode']) &
                    (len(instruction.attrib) == 2)):
                d_print("FILES xmlTranslate - error bad instruction attributes")
                exit(ERR_STRUCT_XML)
            self.code.append([instruction.attrib['opcode']])
            try:
                for i in range(len(list(instruction))):
                    inst_arg = instruction.find("arg" + str(i + 1))
                    arg_type = inst_arg.attrib['type']
                    arg_text = inst_arg.text
                    if arg_type == 'var':
                        if checkNameVar(arg_text):
                            self.code[ins_order - 1].append(arg_text)
                    elif arg_type == 'label':
                        if checkLabelName(arg_text):
                            self.code[ins_order - 1].append(arg_text)
                    elif arg_type == 'type':
                        if (arg_text == 'int') | (arg_text == 'string') | (arg_text == 'bool'):
                            self.code[ins_order - 1].append(arg_text)
                    elif arg_type == 'int':
                        if checkValueByType('int', arg_text):
                            self.code[ins_order - 1].append(arg_type + '@' + arg_text)
                    elif arg_type == 'bool':
                        if (arg_text == 'true') | (arg_text == 'false'):
                            self.code[ins_order - 1].append(arg_type + '@' + arg_text)
                    elif arg_type == 'string':
                        if arg_text is None:
                            self.code[ins_order - 1].append(arg_type + '@')
                        elif checkValueByType(arg_type, arg_text):
                            self.code[ins_order - 1].append(arg_type + '@' + arg_text)
                    elif arg_type == 'nil':
                        if arg_text == 'nil':
                            self.code[ins_order - 1].append(arg_type + '@' + arg_text)
                    else:
                        d_print("FILES xmlTranslate - error unknown type")
                        exit(ERR_STRUCT_XML)
            except IndexError:
                d_print("FILES xmlTranslate - error bad instruction attributes")
                exit(ERR_STRUCT_XML)

    # debug function printing array of code
    # variable debug need to be True to make it work
    def printCode(self):
        for i in self.code:
            d_print(i)

    # debug function that prints input in source handle
    # variable debug need to be True to make it work
    def printFiles(self):
        for line in self.srcFileHandle:
            d_print(line)

    # closes opened files
    def closeFiles(self):
        self.inFileHandle.close()
        self.srcFileHandle.close()


class Interpret:
    files = FileProcessor()
    variables = VariableStorage()
    labels = LabelStorage()
    stack = StackStorage()


# --- functions ---

# debug print function, prints messages only if var debug is set on True
# @param message is message to be printed
def d_print(message):
    if debug:
        print(message)


def arg_err():
    exit(ERR_PARAM)


# checks if the given string is correct representation of IPPcode20 variable
# @err if the string is not correct
# @param var_str is the string of variable from IPPcode20
# @return True if its correct
def checkNameVar(var_str):
    if re.match(r'^(\wF)@([\w_\-$&%*!?]+)$', var_str) is not None:
        value = re.match(r'^(\wF)@([\w_\-$&%*!?]+)$', var_str)
        if (value[1] == "GF") | (value[1] == "TF") | (value[1] == "LF"):
            return True  # value[1] + value[2]
        else:
            d_print("OUTF checkNameVar - error nonexisting frame ")
            exit(ERR_STRUCT_XML)
    else:
        d_print("OUTF checkNameVar - error not a variable")
        exit(ERR_STRUCT_XML)


# checks if the given string is correct representation of IPPcode20 label
# @err when the string is not correct
# @param label_str is the string of label from IPPcode20
# @return True if correct
def checkLabelName(label_str):
    if re.match(r'^([\w_\-$&%*!?]+)$', label_str):
        return True
    else:
        d_print("OUTF checkLabelName - error bad label name")
        exit(ERR_STRUCT_XML)


# checks if given string matches one of the used types
# @err when the string does not match any used types
# @param type_str is string of type to be checked
# @return True if the string matches one of the used types
def checkType(type_str):
    if (type_str == 'int') | (type_str == 'string') | \
            (type_str == 'bool') | (type_str == 'nil'):
        return True
    else:
        d_print("OUTF checkType - error undefined type")
        exit(ERR_STRUCT_XML)


# checks if given value corresponds with given type meanwhile
# the type is also being checked if it exists
# @err when given value does not correspond with given type
# @param type_str is string of the type to be checked for
# @param value_str is string of the value to be checked
# @return True if successful
def checkValueByType(type_str, value_str):
    checkType(type_str)
    if type_str == 'int':
        if re.match(r'^\d+$', value_str) is not None:
            return True
        else:
            d_print("OUTF checkValueByType - error bad integer")
            exit(ERR_STRUCT_XML)
    elif type_str == 'string':
        for ic in range(len(value_str)):
            if value_str[ic] == '\\':
                try:
                    if ((value_str[ic + 1] >= '0') & (value_str[ic + 1] <= '9')) & \
                            ((value_str[ic + 2] >= '0') & (value_str[ic + 2] <= '9')) & \
                            ((value_str[ic + 3] >= '0') & (value_str[ic + 3] <= '9')):
                        ic += 3
                    else:
                        d_print("OUTF checkValueByType - error bad string")
                        exit(ERR_STRUCT_XML)
                except IndexError:
                    d_print("OUTF checkValueByType - error bad string")
                    exit(ERR_STRUCT_XML)
        return True
    elif type_str == 'bool':
        if (value_str == 'true') | (value_str == 'false'):
            return True
        else:
            d_print('OUTF checkValueByType - error bad boolean')
            exit(ERR_STRUCT_XML)
    elif type_str == 'nil':
        if value_str == 'nil':
            return True
        else:
            d_print("OUTF checkValueByType - error bad nil")
            exit(ERR_STRUCT_XML)


def isOpcode(opcode_str):
    for opcode in rules:
        if opcode_str == opcode[0]:
            return True
    return False


# --- main ---

testfile = FileProcessor()
testfile.setHandles()
testfile.xmlTranslate()
testfile.printCode()
testfile.closeFiles()

"""
# variable storage test bench - working as expected
variables = VariableStorage()
variables.createVar('GF@var1')
variables.setVar('GF@var1', 'int', '5')
variables.createTempFrame()
variables.createVar('TF@variableTF')
variables.pushLocFrame()
variables.printStat()
print(variables.getVarValByType('LF@variableTF', 'undef'))
variables.popLocFrame()
variables.printStat()
print(variables.getVarValByType('TF@variableTF', 'undef'))
"""

"""
# frame test bench - work as expected
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

# temp code to test input and source - work as expected
d_print('READ - src file')

for line in srcHandle:
    print(line)

d_print('READ -in file')

for line in inHandle:
    print(line)
"""
